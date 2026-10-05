<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DriverAvailability;
use App\Models\Errand;
use App\Models\Locality;
use App\Models\LogisticsJob;
use App\Models\RiderBaseOperation;
use App\Models\User;
use App\Services\JobMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverWorkLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;

    private User $other;

    private Locality $locality;

    protected function setUp(): void
    {
        parent::setUp();
        $district = District::create(['name' => 'Workflow district', 'is_active' => true]);
        $this->locality = Locality::create(['district_id' => $district->id, 'name' => 'Workflow locality', 'is_active' => true]);
        $this->driver = $this->driver('work-driver@test.example');
        $this->other = $this->driver('other-driver@test.example');
    }

    private function driver(string $email): User
    {
        $user = User::create(['name' => 'Workflow driver', 'email' => $email, 'email_index' => User::emailIndex($email), 'password' => bcrypt('Password123!'), 'role' => 'driver', 'is_active' => true]);
        DriverAvailability::create(['user_id' => $user->id, 'is_online' => true]);
        $base = RiderBaseOperation::create(['user_id' => $user->id, 'district_id' => $this->locality->district_id]);
        $base->localities()->sync([$this->locality->id]);

        return $user;
    }

    private function work(bool $errand, array $extra = []): LogisticsJob|Errand
    {
        if (! $errand) {
            return LogisticsJob::create(array_merge(['type' => 'pickup', 'district_id' => $this->locality->district_id, 'locality_id' => $this->locality->id, 'status' => 'requested'], $extra));
        }

        return Errand::create(array_merge(['code' => 'ER-WORK1', 'description' => 'Collect a workflow test parcel', 'contact_name' => 'Workflow customer', 'contact_phone' => '9876543210', 'contact_phone_index' => User::phoneIndex('9876543210'), 'pickup_district_id' => $this->locality->district_id, 'pickup_locality_id' => $this->locality->id, 'drop_district_id' => $this->locality->district_id, 'drop_locality_id' => $this->locality->id, 'status' => 'requested'], $extra));
    }

    public function test_jobs_and_errands_remain_visible_through_completion_and_cannot_be_stolen_or_regressed(): void
    {
        foreach ([false, true] as $errand) {
            $work = $this->work($errand);
            $base = $errand ? '/api/v1/errands' : '/api/v1/logistics/jobs';
            $this->actingAs($this->driver, 'sanctum')->getJson($base)->assertJsonFragment(['id' => $work->id]);
            $this->postJson($base.'/'.$work->id.'/accept')->assertOk()->assertJsonPath('data.status', 'accepted');
            $this->getJson($base)->assertJsonFragment(['id' => $work->id, 'status' => 'accepted']);
            $this->actingAs($this->other, 'sanctum')->postJson($base.'/'.$work->id.'/accept')->assertUnprocessable();
            $this->postJson($base.'/'.$work->id.'/status', ['status' => 'in_progress'])->assertForbidden();
            $this->actingAs($this->driver, 'sanctum')->postJson($base.'/'.$work->id.'/status', ['status' => 'completed'])->assertUnprocessable();
            $this->postJson($base.'/'.$work->id.'/status', ['status' => 'in_progress'])->assertOk();
            $this->postJson($base.'/'.$work->id.'/status', ['status' => 'completed'])->assertOk();
            $this->postJson($base.'/'.$work->id.'/status', ['status' => 'in_progress'])->assertUnprocessable();
            $this->postJson($base.'/'.$work->id.'/status', ['status' => 'completed'])->assertOk();
            $this->getJson($base)->assertJsonFragment(['id' => $work->id, 'status' => 'completed']);
        }
    }

    public function test_claim_requires_online_active_base_and_excludes_collector_jobs(): void
    {
        $work = $this->work(false);
        $this->driver->driverAvailability->update(['is_online' => false]);
        $this->actingAs($this->driver, 'sanctum')->postJson('/api/v1/logistics/jobs/'.$work->id.'/accept')->assertUnprocessable();
        $this->driver->driverAvailability->update(['is_online' => true]);
        $this->locality->update(['is_active' => false]);
        $this->postJson('/api/v1/logistics/jobs/'.$work->id.'/accept')->assertUnprocessable();
        $this->locality->update(['is_active' => true]);
        $collection = $this->work(false, ['type' => 'collect_produce']);
        $this->postJson('/api/v1/logistics/jobs/'.$collection->id.'/accept')->assertForbidden();
        $this->assertNull($collection->fresh()->driver_id);
        $this->getJson('/api/v1/logistics/jobs')->assertJsonCount(1, 'data');
    }

    public function test_disabled_drivers_are_excluded_from_locality_and_district_matching(): void
    {
        $this->driver->update(['is_active' => false]);
        $matcher = app(JobMatchingService::class);
        $this->assertFalse($matcher->eligibleDrivers($this->locality->id)->contains('id', $this->driver->id));
        $this->assertFalse($matcher->eligibleDriversInDistrict($this->locality->district_id)->contains('id', $this->driver->id));
        $this->assertTrue($matcher->eligibleDrivers($this->locality->id)->contains('id', $this->other->id));
    }

    public function test_assigned_offer_is_visible_even_without_a_base_and_is_reserved_for_its_driver(): void
    {
        $work = $this->work(false, ['status' => 'assigned', 'driver_id' => $this->driver->id]);
        $this->driver->riderBaseOperation->delete();
        $this->actingAs($this->driver, 'sanctum')->getJson('/api/v1/logistics/jobs')->assertJsonFragment(['id' => $work->id]);
        $this->actingAs($this->other, 'sanctum')->getJson('/api/v1/logistics/jobs')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/logistics/jobs/'.$work->id.'/accept')->assertUnprocessable();
        $this->actingAs($this->driver, 'sanctum')->postJson('/api/v1/logistics/jobs/'.$work->id.'/accept')->assertOk();
    }
}
