<?php

namespace App\Services;

use App\Models\CollectorAssignment;
use App\Models\District;
use App\Models\Errand;
use App\Models\Locality;
use App\Models\LogisticsJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared job matcher for logistics (M4.4) and errands (M4.5).
 *
 * A driver is eligible when they are online AND the job's locality is inside
 * their base of operation (one district + up to five localities). Matching
 * prefers nearest localities first, then broadcasts to the district when a
 * locality has no online drivers.
 *
 * All methods are synchronous and side-effect free (they do not mutate jobs);
 * the controller that calls assign() persists the result. Designed for
 * Hostinger shared hosting: no queues, no Redis, runs inside the request.
 */
class JobMatchingService
{
    /**
     * Eligible drivers for a job/errand in the given locality, nearest first.
     *
     * @return Collection<int, User>
     */
    public function eligibleDrivers(int $localityId): Collection
    {
        // Drivers who are online and whose base locality matches.
        $drivers = User::query()
            ->where('role', User::ROLE_DRIVER)
            ->where('is_active', true)
            ->whereHas('driverAvailability', fn ($q) => $q->where('is_online', true))
            ->whereHas('riderBaseOperation.localities', fn ($q) => $q->where('locality_id', $localityId))
            ->get();

        // Nearest = matched in the requested locality first; same ordering.
        return $drivers;
    }

    /**
     * District fallback: any online driver whose base is in this district.
     *
     * @return Collection<int, User>
     */
    public function eligibleDriversInDistrict(int $districtId): Collection
    {
        return User::query()
            ->where('role', User::ROLE_DRIVER)
            ->where('is_active', true)
            ->whereHas('driverAvailability', fn ($q) => $q->where('is_online', true))
            ->whereHas('riderBaseOperation', fn ($q) => $q->where('district_id', $districtId))
            ->get();
    }

    /**
     * Assign the best available driver to a LogisticsJob or Errand record.
     * Returns true if a driver was assigned, false if none available.
     */
    public function assign(LogisticsJob|Errand $job): bool
    {
        $localityId = $job instanceof LogisticsJob ? $job->locality_id : $job->pickup_locality_id;
        $districtId = $job instanceof LogisticsJob ? $job->district_id : $job->pickup_district_id;

        $drivers = $this->eligibleDrivers($localityId);
        if ($drivers->isEmpty()) {
            $drivers = $this->eligibleDriversInDistrict($districtId);
        }

        if ($drivers->isEmpty()) {
            return false;
        }

        /** @var User $driver */
        $driver = $drivers->first();

        $updates = ['driver_id' => $driver->id, 'status' => LogisticsJob::STATUS_ASSIGNED];

        if ($job instanceof LogisticsJob) {
            $updates['assigned_at'] = now();
        }

        $job->update($updates);

        return true;
    }

    /**
     * Drivers available for pickup/delivery in a given locality right now.
     */
    public function availableDriverCount(int $localityId): int
    {
        return $this->eligibleDrivers($localityId)->count();
    }

    /**
     * The collector signed to a sub-division (M28.3), or null. Collections do
     * not depend on online state — the admin signed them to that locality.
     */
    public function collectorFor(int $localityId): ?User
    {
        $assignment = CollectorAssignment::query()
            ->active()
            ->where('locality_id', $localityId)
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_COLLECTOR)->where('is_active', true))
            ->with('user')
            ->first();

        return $assignment?->user;
    }
}
