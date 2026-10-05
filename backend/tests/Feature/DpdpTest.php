<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\DataRequest;
use App\Models\District;
use App\Models\Errand;
use App\Models\Locality;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DpdpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'user@test.com', string $role = 'buyer'): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => $email,
            'email_index' => BlindIndex::make($email),
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_consent_cannot_be_granted_for_another_account_or_fabricated_subject(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('other-consent@fixture.test');
        $consent = ['consent_key' => Consent::KEY_NOTIFICATIONS, 'text_version' => '1.0',
            'purpose' => 'Push notices', 'subject_type' => User::class, 'subject_id' => $other->id];
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/consents', $consent)
            ->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $consent['subject_type'] = 'invented';
        $consent['subject_id'] = $user->id;
        $this->postJson('/api/v1/consents', $consent)->assertUnprocessable();
        $this->assertDatabaseCount('consents', 0);
    }

    public function test_errand_requires_consent_and_records_guest_and_authenticated_contact_permission(): void
    {
        $district = District::create(['name' => 'Fixture district', 'is_active' => true]);
        $locality = Locality::create(['name' => 'Fixture locality', 'district_id' => $district->id,
            'is_active' => true, 'is_service_area_active' => true]);
        $payload = ['contact_name' => 'Errand fixture contact', 'contact_phone' => '+555112233',
            'description' => 'Collect the parcel', 'pickup_district_id' => $district->id,
            'pickup_locality_id' => $locality->id, 'drop_district_id' => $district->id,
            'drop_locality_id' => $locality->id];
        $this->postJson('/api/v1/errands', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('accept_contact');
        $this->assertDatabaseCount('errands', 0);
        $this->assertDatabaseCount('consents', 0);
        $payload['accept_contact'] = true;
        $guest = $this->postJson('/api/v1/errands', $payload)->assertCreated();
        $guestConsent = Consent::query()->where('subject_type', Errand::class)
            ->where('subject_id', $guest->json('data.id'))->firstOrFail();
        $this->assertSame(Consent::KEY_ERRAND_CONTACT, $guestConsent->consent_key);
        $this->assertSame('1.0', $guestConsent->text_version);
        $this->assertNotNull($guestConsent->granted_at);
        $user = $this->makeUser('errand@fixture.test');
        $token = $user->createToken('Fixture app')->plainTextToken;
        $created = $this->withToken($token)->postJson('/api/v1/errands', $payload)
            ->assertCreated()->assertJsonPath('data.customer_id', $user->id);
        $id = $created->json('data.id');
        $consent = Consent::query()->where('subject_type', Errand::class)->where('subject_id', $id)->firstOrFail();
        $this->getJson('/api/v1/consents')->assertOk()->assertJsonPath('data.0.id', $consent->id);
        $this->deleteJson('/api/v1/consents/'.$consent->id)->assertOk();
        $this->assertNotNull($consent->fresh()->revoked_at);
    }

    public function test_a_user_can_record_consent(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/consents', [
                'consent_key' => Consent::KEY_REGISTRATION,
                'text_version' => '1.0',
                'purpose' => 'Create an account and use the platform',
                'subject_type' => User::class,
                'subject_id' => $user->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.consent_key', Consent::KEY_REGISTRATION);
    }

    public function test_a_user_can_list_their_consents(): void
    {
        $this->withoutExceptionHandling();
        $user = $this->makeUser();

        $consent = Consent::query()->create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'consent_key' => Consent::KEY_REGISTRATION,
            'text_version' => '1.0',
            'purpose' => 'Registration',
            'granted_at' => now(),
            'revoked_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/consents');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_can_revoke_consent(): void
    {
        $user = $this->makeUser();

        $consent = Consent::query()->create([
            'subject_type' => User::class,
            'subject_id' => (int) $user->id,
            'consent_key' => Consent::KEY_NOTIFICATIONS,
            'text_version' => '1.0',
            'purpose' => 'Notifications',
            'granted_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/consents/{$consent->id}");

        $response->assertOk()
            ->assertJsonPath('data.revoked_at', fn ($v) => $v !== null);
    }

    public function test_a_user_can_export_their_data(): void
    {
        $user = $this->makeUser('export@test.com');
        Storage::fake('local');
        Storage::fake('public');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/export');

        $response->assertCreated()
            ->assertJsonPath('data.status', DataRequest::STATUS_COMPLETED)
            ->assertJsonPath('data.download_url', fn ($v) => is_string($v) && str_contains($v, 'signature='));

        $dataRequest = DataRequest::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertStringStartsWith('private:exports/', $dataRequest->notes);
        $privatePath = substr($dataRequest->notes, strlen('private:'));
        Storage::disk('local')->assertExists($privatePath);
        $this->assertSame([], Storage::disk('public')->allFiles('exports'));

        $downloadUrl = (string) $response->json('data.download_url');
        $parts = parse_url($downloadUrl);
        $signedPath = $parts['path'].'?'.$parts['query'];

        $this->get($signedPath)
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertDownload('shekuthi-data-export-'.$dataRequest->id.'.json');

        $this->assertStringContainsString(
            'export@test.com',
            Storage::disk('local')->get($privatePath),
        );

        $this->get('/api/v1/exports/'.$dataRequest->id.'/download')
            ->assertForbidden();

        $this->assertDatabaseHas('data_requests', [
            'user_id' => $user->id,
            'type' => DataRequest::TYPE_EXPORT,
            'status' => DataRequest::STATUS_COMPLETED,
        ]);
    }

    public function test_a_user_can_request_deletion(): void
    {
        $user = $this->makeUser('delete@test.com');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/deletion');

        $response->assertCreated()
            ->assertJsonPath('data.status', DataRequest::STATUS_COMPLETED);

        // Verify the user's PII is anonymized.
        $user->refresh();
        $this->assertEquals('Deleted User', $user->name);
        $this->assertNull($user->phone);
        $this->assertStringContainsString('deleted-'.$user->id, $user->email);

        $this->assertDatabaseHas('data_requests', [
            'user_id' => $user->id,
            'type' => DataRequest::TYPE_DELETION,
            'status' => DataRequest::STATUS_COMPLETED,
        ]);
    }
}
