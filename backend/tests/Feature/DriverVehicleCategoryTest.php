<?php

namespace Tests\Feature;

use App\Models\TransportCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverVehicleCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): User
    {
        return User::query()->create(['name' => 'Vehicle test driver', 'email' => 'vehicle@test.example',
            'email_index' => User::emailIndex('vehicle@test.example'), 'role' => 'driver',
            'password' => bcrypt('Password123!'), 'is_active' => true]);
    }

    public function test_vehicle_is_required_before_work_profile_submission_and_does_not_partially_update(): void
    {
        $user = $this->driver();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile', [
            'name' => 'Changed driver', 'transport_category_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('vehicle_category');
        $this->assertSame('Vehicle test driver', $user->fresh()->name);
        $this->actingAs($user)->put(route('dashboard.driver.profile'), ['name' => 'Changed driver'])
            ->assertSessionHasErrors('vehicle_category');
        $this->assertNull($user->fresh()->vehicle_category);
        $this->getJson('/api/v1/transport')->assertJsonCount(0, 'data');
    }

    public function test_all_vehicle_choices_round_trip_and_are_displayed_independently_of_work(): void
    {
        $user = $this->driver();
        $work = TransportCategory::query()->create(['name' => 'Parcel errands', 'is_active' => true]);
        foreach (User::VEHICLE_CATEGORIES as $category => $label) {
            $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile', [
                'vehicle_category' => $category, 'transport_category_ids' => [$work->id],
            ])->assertOk()->assertJsonPath('user.driver.vehicle_category', $category)
                ->assertJsonPath('user.driver.vehicle_categories.'.$category, $label);
            $this->assertSame($category, $user->fresh()->profile()['vehicle_category']);
            $this->getJson('/api/v1/transport')->assertOk()
                ->assertJsonPath('data.0.vehicle_category', $category)
                ->assertJsonPath('data.0.vehicle_category_name', $label);
            $this->get('/transport')->assertOk()->assertSee($label)->assertSee('Parcel errands');
        }
    }

    public function test_invalid_vehicle_is_rejected_and_only_drivers_may_select_it(): void
    {
        $user = $this->driver();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile', ['vehicle_category' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle_category');
        $this->actingAs($user)->put(route('dashboard.driver.profile'), ['name' => $user->name, 'vehicle_category' => 'invalid'])
            ->assertSessionHasErrors('vehicle_category');
        $user->update(['role' => 'vendor']);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile', ['vehicle_category' => 'car'])
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle_category');
        $this->assertNull($user->fresh()->vehicle_category);
    }

    public function test_legacy_driver_is_hidden_until_a_vehicle_is_chosen_and_contact_only_updates_still_work(): void
    {
        $user = $this->driver();
        $this->get('/transport')->assertOk()->assertDontSee('Vehicle test driver');
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile', ['name' => 'Updated vehicle driver'])->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Choose a vehicle category');
        $this->actingAs($user)->put(route('dashboard.driver.profile'), ['name' => 'Updated vehicle driver', 'vehicle_category' => 'three_wheeler'])
            ->assertRedirect(route('dashboard'));
        $this->get('/transport')->assertSee('Updated vehicle driver')->assertSee('Three-wheeler');
        $this->assertSame('three_wheeler', $user->fresh()->vehicle_category);
    }
}
