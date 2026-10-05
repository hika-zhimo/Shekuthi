<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\District;
use App\Models\Locality;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Service areas (M9.1): the admin ticks localities active; users then only
 * see — and every write path only accepts — areas the platform operates in.
 */
class ServiceAreaTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $email): User
    {
        return User::query()->create([
            'name' => 'Test '.$role,
            'email' => $email,
            'email_index' => BlindIndex::make($email),
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: District, 1: Locality, 2: Locality}
     *                                                      [district, active locality, inactive locality]
     */
    private function makeArea(): array
    {
        $district = District::query()->create(['name' => 'Dimapur', 'is_active' => true]);
        $active = Locality::query()->create([
            'district_id' => $district->id,
            'name' => 'Served Area',
            'is_active' => true,
        ]);
        $inactive = Locality::query()->create([
            'district_id' => $district->id,
            'name' => 'Quiet Area',
            'is_active' => false,
        ]);

        return [$district, $active, $inactive];
    }

    private function makeVendor(District $district): User
    {
        $user = $this->makeUser(User::ROLE_VENDOR, 'vendor@test.com');

        Vendor::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Test Shop',
            'category' => 'traditional',
            'district_id' => $district->id,
        ]);

        return $user->fresh();
    }

    public function test_locations_only_lists_active_service_areas(): void
    {
        [$district, $active, $inactive] = $this->makeArea();

        $hiddenDistrict = District::query()->create(['name' => 'Closed District', 'is_active' => false]);
        Locality::query()->create([
            'district_id' => $hiddenDistrict->id,
            'name' => 'Behind The Scenes',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk();

        $districts = collect($response->json('data'));

        $this->assertCount(1, $districts);
        $this->assertSame($district->id, $districts->first()['id']);
        $this->assertSame(
            [['id' => $active->id, 'name' => $active->name]],
            $districts->first()['localities'],
        );
    }

    public function test_driver_base_rejects_inactive_localities_and_districts(): void
    {
        [$district, $active, $inactive] = $this->makeArea();
        $driver = $this->makeUser(User::ROLE_DRIVER, 'driver@test.com');

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/driver/base', [
                'district_id' => $district->id,
                'locality_ids' => [$inactive->id],
            ])
            ->assertStatus(422);

        $closedDistrict = District::query()->create(['name' => 'Closed District', 'is_active' => false]);
        $closedLocality = Locality::query()->create([
            'district_id' => $closedDistrict->id,
            'name' => 'Closed Area',
            'is_active' => true,
        ]);

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/driver/base', [
                'district_id' => $closedDistrict->id,
                'locality_ids' => [$closedLocality->id],
            ])
            ->assertStatus(422);

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/driver/base', [
                'district_id' => $district->id,
                'locality_ids' => [$active->id],
            ])
            ->assertCreated();
    }

    public function test_errand_rejects_inactive_pickup_or_drop_localities(): void
    {
        [$district, $active, $inactive] = $this->makeArea();

        $this->postJson('/api/v1/errands', [
            'accept_contact' => true,
            'contact_name' => 'Guest',
            'contact_phone' => '+5550001',
            'description' => 'Pick up a parcel',
            'pickup_district_id' => $district->id,
            'pickup_locality_id' => $inactive->id,
            'drop_district_id' => $district->id,
            'drop_locality_id' => $active->id,
        ])->assertStatus(422);

        $this->postJson('/api/v1/errands', [
            'accept_contact' => true,
            'contact_name' => 'Guest',
            'contact_phone' => '+5550001',
            'description' => 'Pick up a parcel',
            'pickup_district_id' => $district->id,
            'pickup_locality_id' => $active->id,
            'drop_district_id' => $district->id,
            'drop_locality_id' => $active->id,
        ])->assertCreated();
    }

    /**
     * The Active tick is for driver bases and errands, not listings
     * (M10.2): a listing may reference any existing locality. District
     * pairing is still enforced.
     */
    public function test_listings_may_target_inactive_service_areas(): void
    {
        [$district, , $inactive] = $this->makeArea();
        $vendor = $this->makeVendor($district);

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/v1/listings', [
                'title' => 'Bamboo basket',
                'category' => 'traditional',
                'price' => 150,
                'moq' => 1,
                'district_id' => $district->id,
                'locality_id' => $inactive->id,
                'status' => 'active',
            ])
            ->assertCreated();

        $otherDistrict = District::query()->create(['name' => 'Other District', 'is_active' => true]);
        $otherLocality = Locality::query()->create([
            'district_id' => $otherDistrict->id,
            'name' => 'Elsewhere',
            'is_active' => true,
        ]);

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/v1/listings', [
                'title' => 'Mismatched basket',
                'category' => 'traditional',
                'price' => 150,
                'moq' => 1,
                'district_id' => $district->id,
                'locality_id' => $otherLocality->id,
                'status' => 'active',
            ])
            ->assertStatus(422);
    }

    public function test_logistics_job_rejects_inactive_localities(): void
    {
        [$district, $active, $inactive] = $this->makeArea();
        $vendor = $this->makeVendor($district);

        $booking = Booking::query()->create([
            'code' => 'BK-TEST01',
            'vendor_id' => $vendor->vendor->id,
            'status' => Booking::STATUS_CONFIRMED,
            'contact_name' => 'Guest',
            'contact_phone' => '+5550002',
            'contact_phone_index' => BlindIndex::make('+5550002'),
        ]);

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/v1/logistics/jobs', [
                'booking_id' => $booking->id,
                'type' => 'pickup',
                'district_id' => $district->id,
                'locality_id' => $inactive->id,
            ])
            ->assertStatus(422);

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/v1/logistics/jobs', [
                'booking_id' => $booking->id,
                'type' => 'pickup',
                'district_id' => $district->id,
                'locality_id' => $active->id,
            ])
            ->assertCreated();
    }

    /**
     * The catalog shows listings regardless of their area's service flag
     * (M10.2) — the enable/disable does not apply to listings.
     */
    public function test_catalog_shows_listings_in_inactive_service_areas(): void
    {
        [$district, , $inactive] = $this->makeArea();
        $vendor = $this->makeVendor($district);

        Product::query()->create([
            'vendor_id' => $vendor->vendor->id,
            'category' => 'traditional',
            'title' => 'Quiet Area Basket',
            'price' => 90,
            'moq' => 1,
            'district_id' => $district->id,
            'locality_id' => $inactive->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Quiet Area Basket']);

        $web = $this->get('/catalog');
        $web->assertOk();
        $this->assertStringContainsString('Quiet Area Basket', $web->getContent());
    }

    public function test_admin_added_localities_start_inactive_until_ticked(): void
    {
        [$district] = $this->makeArea();
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin@test.com');

        $this->actingAs($admin)
            ->post(route('admin.localities.store', $district), ['name' => 'New Area'])
            ->assertRedirect();

        $this->assertDatabaseHas('localities', [
            'district_id' => $district->id,
            'name' => 'New Area',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.localities.store', $district), [
                'name' => 'Live Area',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('localities', [
            'district_id' => $district->id,
            'name' => 'Live Area',
            'is_active' => true,
        ]);
    }

    /**
     * Admin multi-add (M10.1): one comma-separated submission creates one
     * row per name, de-duplicated case-insensitively within the list.
     */
    public function test_admin_can_add_multiple_comma_separated_localities(): void
    {
        [$district] = $this->makeArea();
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin-multi@test.com');

        $this->actingAs($admin)
            ->post(route('admin.localities.store', $district), [
                'name' => 'Centre, East, West, east',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.localities.index'));

        $this->assertDatabaseHas('localities', ['district_id' => $district->id, 'name' => 'Centre', 'is_active' => true]);
        $this->assertDatabaseHas('localities', ['district_id' => $district->id, 'name' => 'East', 'is_active' => true]);
        $this->assertDatabaseHas('localities', ['district_id' => $district->id, 'name' => 'West', 'is_active' => true]);

        // 'east' de-duplicates against 'East' inside one submission.
        $this->assertSame(1, Locality::query()
            ->where('district_id', $district->id)
            ->whereRaw('lower(name) = ?', ['east'])
            ->count());
    }

    public function test_admin_multi_add_skips_names_that_already_exist(): void
    {
        // makeArea creates 'Served Area' (active) and 'Quiet Area' (inactive).
        [$district] = $this->makeArea();
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin-skip@test.com');

        $this->actingAs($admin)
            ->post(route('admin.localities.store', $district), [
                'name' => 'Served Area, New One',
            ])
            ->assertRedirect(route('admin.localities.index'));

        // The existing row keeps its state; only the new name is added, and
        // without the Active tick it starts hidden.
        $this->assertDatabaseHas('localities', [
            'district_id' => $district->id,
            'name' => 'Served Area',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('localities', [
            'district_id' => $district->id,
            'name' => 'New One',
            'is_active' => false,
        ]);
        $this->assertSame(1, Locality::query()
            ->where('district_id', $district->id)
            ->where('name', 'New One')
            ->count());
    }
}
