<?php

namespace Tests\Feature;

use App\Models\DriverAvailability;
use App\Models\TransportCategory;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M18.1: drivers set their public contact phone and the transport/errand work
 * they provide.
 */
class DriverTransportTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriver(string $email = 'transport-driver-profile@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Test Driver',
            'email' => $email,
            'email_index' => BlindIndex::make($email),
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_DRIVER,
            'is_active' => true,
        ]);

        DriverAvailability::query()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_the_dashboard_renders_the_driver_work_form(): void
    {
        TransportCategory::query()->create(['name' => 'Bike delivery', 'is_active' => true]);

        $this->actingAs($this->makeDriver())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Work you provide')
            ->assertSee('Bike delivery')
            ->assertSee(route('dashboard.driver.profile'), false);
    }

    public function test_a_driver_can_save_their_phone_and_categories(): void
    {
        $bike = TransportCategory::query()->create(['name' => 'Bike delivery']);
        $errand = TransportCategory::query()->create(['name' => 'Errand runner']);
        $driver = $this->makeDriver();

        $this->actingAs($driver)
            ->put(route('dashboard.driver.profile'), [
                'vehicle_category' => 'two_wheeler',
                'name' => 'Rider Ravi',
                'phone' => '9876507777',
                'transport_category_ids' => [$bike->id, $errand->id],
            ])
            ->assertRedirect(route('dashboard'));

        $driver->refresh();

        $this->assertSame('Rider Ravi', $driver->name);
        $this->assertSame('9876507777', $driver->phone);
        $this->assertSame(
            [$bike->id, $errand->id],
            $driver->transportCategories()->orderBy('transport_categories.id')->pluck('transport_categories.id')->all(),
        );
    }

    public function test_retired_categories_cannot_be_selected(): void
    {
        $retired = TransportCategory::query()->create(['name' => 'Old Route', 'is_active' => false]);
        $driver = $this->makeDriver('retired-transport@test.com');

        $this->actingAs($driver)
            ->put(route('dashboard.driver.profile'), [
                'name' => 'Rider',
                'transport_category_ids' => [$retired->id],
            ])
            ->assertSessionHasErrors('transport_category_ids.0');
    }

    public function test_non_drivers_cannot_save_the_driver_profile(): void
    {
        $worker = User::query()->create([
            'name' => 'Worker',
            'email' => 'not-a-driver@test.com',
            'email_index' => BlindIndex::make('not-a-driver@test.com'),
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_SKILLED_WORKER,
            'is_active' => true,
        ]);

        $this->actingAs($worker)
            ->put(route('dashboard.driver.profile'), ['name' => 'Nope'])
            ->assertSessionHasErrors('role');
    }
}
