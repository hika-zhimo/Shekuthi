<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Locality;
use App\Models\User;
use App\Models\Vendor;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsTest extends TestCase
{
    use RefreshDatabase;

    private function seedDistrictLocalities(): void
    {
        if (District::query()->count() > 0) {
            return;
        }

        $district = District::query()->create(['name' => 'Test District', 'is_active' => true]);
        foreach (['Centre', 'East', 'West'] as $name) {
            Locality::query()->create([
                'district_id' => $district->id,
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }

    private function makeDriver(string $email = 'driver@test.com'): User
    {
        return User::query()->create([
            'name' => 'Test Driver',
            'email' => $email,
            'email_index' => BlindIndex::make($email),
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_DRIVER,
            'is_active' => true,
        ]);
    }

    private function makeVendor(string $email = 'vendor@test.com'): User
    {
        $this->seedDistrictLocalities();
        $user = User::query()->create([
            'name' => 'Test Vendor',
            'email' => $email,
            'email_index' => BlindIndex::make($email),
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_VENDOR,
            'is_active' => true,
        ]);

        Vendor::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Test Shop',
            'category' => 'traditional',
            'district_id' => 1,
        ]);

        return $user->fresh();
    }

    public function test_a_driver_can_set_a_base_of_operation_with_max_5_localities(): void
    {
        $this->seedDistrictLocalities();
        $driver = $this->makeDriver();
        $localities = Locality::query()->where('district_id', 1)->get();

        $response = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/driver/base', [
                'district_id' => 1,
                'locality_ids' => $localities->pluck('id')->toArray(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.locality_count', $localities->count());
    }

    public function test_a_driver_base_rejects_more_than_5_localities(): void
    {
        $this->seedDistrictLocalities();
        $driver = $this->makeDriver('driver2@test.com');

        // Create extra localities to exceed the limit.
        for ($i = 0; $i < 4; $i++) {
            Locality::query()->create(['district_id' => 1, 'name' => "Loc $i", 'is_active' => true]);
        }

        $localities = Locality::query()->where('district_id', 1)->get();

        $response = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/driver/base', [
                'district_id' => 1,
                'locality_ids' => $localities->pluck('id')->take(7)->toArray(),
            ]);

        $response->assertStatus(422);
    }

    public function test_a_driver_can_toggle_online_offline_availability(): void
    {
        $driver = $this->makeDriver('driver3@test.com');

        $response = $this->actingAs($driver, 'sanctum')
            ->putJson('/api/v1/driver/availability', ['is_online' => true]);

        $response->assertOk()
            ->assertJsonPath('data.is_online', true);
        $this->assertNull($response->json('data.last_online_at'));

        $response = $this->actingAs($driver, 'sanctum')
            ->putJson('/api/v1/driver/availability', ['is_online' => false]);

        $response->assertOk()
            ->assertJsonPath('data.is_online', false);
        $this->assertNotNull($response->json('data.last_online_at'));
    }

    public function test_non_driver_cannot_set_base_of_operation(): void
    {
        $vendor = $this->makeVendor('vendor2@test.com');

        $response = $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/v1/driver/base', [
                'district_id' => 1,
                'locality_ids' => [1],
            ]);

        $response->assertStatus(422);
    }

    public function test_a_guest_can_create_an_errand_with_contact_info(): void
    {
        $this->seedDistrictLocalities();
        $district = District::query()->first();
        $localities = Locality::query()->where('district_id', $district->id)->get();

        $response = $this->postJson('/api/v1/errands', [
            'accept_contact' => true,
            'contact_name' => 'Guest User',
            'contact_phone' => '+5557777',
            'description' => 'Please pick up my package from the station',
            'pickup_district_id' => $district->id,
            'pickup_locality_id' => $localities[0]->id,
            'drop_district_id' => $district->id,
            'drop_locality_id' => $localities[1]->id,
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertStringStartsWith('ER-', $content['data']['code']);
        $this->assertFalse($content['assigned_driver']);
    }

    public function test_errand_creation_rejects_mismatched_locality_and_district(): void
    {
        $this->seedDistrictLocalities();

        $district2 = District::query()->create(['name' => 'District Two', 'is_active' => true]);
        $locality2 = Locality::query()->create([
            'district_id' => $district2->id,
            'name' => 'Another Locality',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/errands', [
            'accept_contact' => true,
            'contact_name' => 'Guest User',
            'contact_phone' => '+5557777',
            'description' => 'Test',
            'pickup_district_id' => 1,
            'pickup_locality_id' => $locality2->id,
            'drop_district_id' => $district2->id,
            'drop_locality_id' => $locality2->id,
        ]);

        $response->assertStatus(422);
    }
}
