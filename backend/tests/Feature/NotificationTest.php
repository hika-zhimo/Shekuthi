<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Consent;
use App\Models\DeviceToken;
use App\Models\District;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\GenericNotification;
use App\Services\BookingService;
use App\Services\NotificationService;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'user@test.com', string $role = 'vendor'): User
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

    public function test_device_registration_requires_permission_and_does_not_transfer_ownership(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('other-push@fixture.test');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', ['token' => 'fixture-token'])
            ->assertUnprocessable()->assertJsonValidationErrors('notification_consent');
        $this->assertDatabaseCount('device_tokens', 0);
        $this->assertDatabaseCount('consents', 0);
        $this->postJson('/api/v1/device-tokens', ['token' => 'fixture-token', 'notification_consent' => true])->assertCreated();
        $this->postJson('/api/v1/device-tokens', ['token' => 'fixture-token', 'notification_consent' => true])->assertCreated();
        $this->assertDatabaseCount('consents', 1);
        $consent = Consent::firstOrFail();
        $this->assertSame(Consent::KEY_NOTIFICATIONS, $consent->consent_key);
        $this->assertSame('1.0', $consent->text_version);
        $this->assertNotNull($consent->granted_at);
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fixture-token', 'notification_consent' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertSame($user->id, DeviceToken::firstOrFail()->user_id);
    }

    public function test_push_requires_active_consent_and_revocation_removes_devices(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 200)]);
        config(['services.fcm.server_key' => 'isolated-fixture-key']);
        $user = $this->makeUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'fixture-token', 'platform' => 'android']);
        app(NotificationService::class)->push($user, 'Account notice', 'Own notice');
        Http::assertNothingSent();
        $this->assertCount(1, $user->notifications()->get()); // operational inbox remains
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fixture-token', 'notification_consent' => true,
        ])->assertCreated();
        app(NotificationService::class)->push($user, 'Account notice', 'Own notice');
        Http::assertSentCount(1);
        $consent = Consent::where('consent_key', Consent::KEY_NOTIFICATIONS)->firstOrFail();
        $this->deleteJson('/api/v1/consents/'.$consent->id)->assertOk();
        $this->assertSame(0, $user->deviceTokens()->count());
        app(NotificationService::class)->push($user, 'Account notice', 'Own notice');
        Http::assertSentCount(1);
        $user->update(['is_active' => false]);
        app(NotificationService::class)->push($user, 'Account notice', 'Own notice');
        $this->assertCount(3, $user->notifications()->get());
    }

    public function test_a_user_can_register_a_device_token(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/device-tokens', [
                'notification_consent' => true,
                'token' => 'fcm-token-abc123',
                'platform' => 'android',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.token', 'fcm-token-abc123')
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'platform' => 'android',
        ]);
    }

    public function test_a_user_can_remove_a_device_token(): void
    {
        $user = $this->makeUser();

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'fcm-token-toremove',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/device-tokens/fcm-token-toremove');

        $response->assertOk()
            ->assertJsonPath('message', 'Device token removed.');

        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-toremove',
        ]);
    }

    public function test_a_user_cannot_remove_someone_elses_token(): void
    {
        $owner = $this->makeUser('owner@test.com');
        $other = $this->makeUser('other@test.com');

        DeviceToken::query()->create([
            'user_id' => $owner->id,
            'token' => 'fcm-token-owner',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->deleteJson('/api/v1/device-tokens/fcm-token-owner');

        $response->assertStatus(422);
    }

    public function test_booking_status_change_writes_in_app_notification(): void
    {
        NotificationFacade::fake();

        $user = $this->makeUser('vendor@test.com', 'vendor');
        $district = District::query()->create(['name' => 'Test District', 'is_active' => true]);
        $vendor = Vendor::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Test Shop',
            'category' => 'traditional',
            'district_id' => $district->id,
        ]);

        $booking = Booking::query()->create([
            'code' => 'BK-NOTIF01',
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'contact_name' => 'Guest',
            'contact_phone' => '+555111',
            'contact_phone_index' => BlindIndex::make('+555111'),
            'settled_offline' => true,
        ]);

        $service = new BookingService;
        $service->changeStatus($booking, 'confirmed');

        NotificationFacade::assertSentTo(
            $user,
            GenericNotification::class
        );
    }
}
