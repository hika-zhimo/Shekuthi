<?php

namespace Tests\Feature;

use App\Models\DriverAvailability;
use App\Models\TransportCategory;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M18.2: public Transport & errands directory — all active drivers, online
 * badge, category filter, and a public call button.
 */
class TransportDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriver(
        string $name,
        string $phone = '9876508888',
        bool $online = false,
        bool $active = true,
        array $categoryIds = [],
    ): User {
        $email = strtolower(str_replace(' ', '.', $name)).'@test.com';

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'email_index' => BlindIndex::make($email),
            'phone' => $phone,
            'phone_index' => $phone !== '' ? BlindIndex::make($phone) : null,
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_DRIVER,
            'vehicle_category' => 'two_wheeler',
            'is_active' => $active,
        ]);

        DriverAvailability::query()->create([
            'user_id' => $user->id,
            'is_online' => $online,
        ]);

        if ($categoryIds !== []) {
            $user->transportCategories()->sync($categoryIds);
        }

        return $user->fresh();
    }

    public function test_the_directory_lists_drivers_with_a_call_button_and_online_badge(): void
    {
        $this->makeDriver('Ravi Rider', '9876508888', online: true);

        $this->get(route('transport'))
            ->assertOk()
            ->assertSee('Ravi Rider')
            ->assertSee('9876508888')
            ->assertSee('tel:9876508888', false)
            ->assertSee('Online');
    }

    public function test_offline_drivers_are_still_listed(): void
    {
        $this->makeDriver('Offline Omar', online: false);

        $this->get(route('transport'))
            ->assertOk()
            ->assertSee('Offline Omar')
            ->assertSee('Offline');
    }

    public function test_the_directory_can_filter_by_work_category(): void
    {
        $bike = TransportCategory::query()->create(['name' => 'Bike delivery']);
        $truck = TransportCategory::query()->create(['name' => 'Truck / tempo']);

        $this->makeDriver('Bike Bala', '9876500001', categoryIds: [$bike->id]);
        $this->makeDriver('Truck Tara', '9876500002', categoryIds: [$truck->id]);

        $this->get(route('transport', ['category_id' => $bike->id]))
            ->assertOk()
            ->assertSee('Bike Bala')
            ->assertDontSee('Truck Tara');
    }

    public function test_inactive_drivers_are_hidden(): void
    {
        $this->makeDriver('Gone Driver', active: false);

        $this->get(route('transport'))
            ->assertOk()
            ->assertDontSee('Gone Driver');
    }

    public function test_the_api_returns_drivers_with_contact_and_categories(): void
    {
        $bike = TransportCategory::query()->create(['name' => 'Bike delivery']);
        $this->makeDriver('Api Ravi', '9876509999', online: true, categoryIds: [$bike->id]);

        $this->getJson('/api/v1/transport')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Api Ravi'])
            ->assertJsonFragment(['phone' => '9876509999'])
            ->assertJsonFragment(['is_online' => true])
            ->assertJsonFragment(['name' => 'Bike delivery']);

        $this->getJson('/api/v1/transport?category_id='.$bike->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Api Ravi']);
    }
}
