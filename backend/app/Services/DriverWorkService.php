<?php

namespace App\Services;

use App\Models\Errand;
use App\Models\LogisticsJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverWorkService
{
    public function queue(User $driver, bool $errands = false): Builder
    {
        abort_unless($driver->role === User::ROLE_DRIVER, 403);
        $class = $errands ? Errand::class : LogisticsJob::class;
        $locality = $errands ? 'pickup_locality_id' : 'locality_id';
        $ids = $this->localities($driver);
        $online = $driver->is_active && (bool) $driver->driverAvailability?->is_online;

        return $class::query()->when(! $errands, fn ($q) => $q->whereIn('type', ['pickup', 'delivery']))
            ->where(function (Builder $q) use ($driver, $locality, $ids, $online): void {
                $q->where(function (Builder $own) use ($driver): void {
                    $own->where('driver_id', $driver->id)->whereIn('status', ['assigned', 'accepted', 'in_progress', 'completed']);
                });
                if ($online) {
                    $q->orWhere(fn (Builder $available) => $available->whereNull('driver_id')
                        ->where('status', 'requested')->whereIn($locality, $ids));
                }
            });
    }

    public function accept(User $driver, LogisticsJob|Errand $work): LogisticsJob|Errand
    {
        return DB::transaction(function () use ($driver, $work) {
            $locked = $work->newQuery()->lockForUpdate()->findOrFail($work->id);
            $this->driverOnly($driver, $locked);
            $ownedOffer = $locked->status === 'assigned' && $locked->driver_id === $driver->id;
            $locality = $locked instanceof Errand ? $locked->pickup_locality_id : $locked->locality_id;
            $available = $locked->status === 'requested' && $locked->driver_id === null
                && $driver->is_active && $driver->driverAvailability?->is_online
                && in_array($locality, $this->localities($driver), true);
            if (! $ownedOffer && ! $available) {
                throw ValidationException::withMessages(['id' => ['This work is not available to you. Refresh your queue.']]);
            }
            $locked->update(['driver_id' => $driver->id, 'status' => 'accepted']);

            return $locked;
        });
    }

    public function progress(User $driver, LogisticsJob|Errand $work, string $status): LogisticsJob|Errand
    {
        return DB::transaction(function () use ($driver, $work, $status) {
            $locked = $work->newQuery()->lockForUpdate()->findOrFail($work->id);
            $this->driverOnly($driver, $locked);
            abort_unless($locked->driver_id === $driver->id, 403);
            if ($locked->status === $status && in_array($status, ['in_progress', 'completed'], true)) {
                return $locked;
            }
            $next = ['accepted' => 'in_progress', 'in_progress' => 'completed'];
            if (($next[$locked->status] ?? null) !== $status) {
                throw ValidationException::withMessages(['status' => ['Start accepted work before completing it. Completed or cancelled work cannot be reopened.']]);
            }
            $data = ['status' => $status];
            if ($locked instanceof LogisticsJob && $status === 'completed') {
                $data['completed_at'] = now();
            }
            $locked->update($data);

            return $locked;
        });
    }

    private function driverOnly(User $driver, LogisticsJob|Errand $work): void
    {
        abort_unless($driver->role === User::ROLE_DRIVER && $driver->is_active, 403);
        abort_if($work instanceof LogisticsJob && $work->type === 'collect_produce', 403);
    }

    private function localities(User $driver): array
    {
        return $driver->riderBaseOperation?->localities()->where('localities.is_active', true)
            ->whereHas('district', fn ($q) => $q->where('is_active', true))
            ->pluck('localities.id')->all() ?? [];
    }
}
