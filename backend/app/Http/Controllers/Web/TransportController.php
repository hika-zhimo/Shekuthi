<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TransportCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public transport & errands directory (M18.2), filterable by transport
 * category. Owner decision: listing is for all active drivers (with an
 * online badge), and their contact phone is shown publicly with a call
 * button. Only active categories/drivers appear.
 */
class TransportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:transport_categories,id'],
        ]);

        $selected = $data['category_id'] ?? null;

        $drivers = User::query()
            ->where('role', User::ROLE_DRIVER)
            ->where('is_active', true)
            ->whereIn('vehicle_category', array_keys(User::VEHICLE_CATEGORIES))
            ->with([
                'driverAvailability',
                'transportCategories' => fn ($query) => $query->where('is_active', true),
                'riderBaseOperation.district',
            ])
            ->when($selected, fn ($query) => $query
                ->whereHas('transportCategories', fn ($categories) => $categories
                    ->where('transport_categories.id', $selected)))
            ->limit(60)
            ->get()
            // Name is encrypted at rest, so order after decrypting; PHP's
            // sort is stable, so the online-first pass keeps that order.
            ->sortBy(fn (User $driver) => mb_strtolower($driver->name))
            ->sortByDesc(fn (User $driver) => (bool) $driver->driverAvailability?->is_online)
            ->values();

        return view('pages.transport', [
            'drivers' => $drivers,
            'categories' => TransportCategory::query()->active()->orderBy('name')->get(),
            'selected' => $selected,
        ]);
    }
}
