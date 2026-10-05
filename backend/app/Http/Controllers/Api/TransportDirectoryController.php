<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransportCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transport & errands directory for the app (M18.2). Public, no auth. Lists
 * active drivers with an online flag and, per the owner's decision, their
 * public contact phone so the app can offer a call action.
 */
class TransportDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:transport_categories,id'],
        ]);

        $drivers = User::query()
            ->where('role', User::ROLE_DRIVER)
            ->where('is_active', true)
            ->whereIn('vehicle_category', array_keys(User::VEHICLE_CATEGORIES))
            ->with([
                'driverAvailability',
                'transportCategories' => fn ($query) => $query->where('is_active', true),
                'riderBaseOperation.district',
            ])
            ->when($data['category_id'] ?? null, fn ($query, $categoryId) => $query
                ->whereHas('transportCategories', fn ($categories) => $categories
                    ->where('transport_categories.id', $categoryId)))
            ->limit(60)
            ->get()
            ->sortBy(fn (User $driver) => mb_strtolower($driver->name))
            ->sortByDesc(fn (User $driver) => (bool) $driver->driverAvailability?->is_online)
            ->values();

        return response()->json([
            'data' => $drivers->map(fn (User $driver) => [
                'id' => $driver->id,
                'name' => $driver->name,
                'vehicle_category' => $driver->vehicle_category,
                'vehicle_category_name' => User::VEHICLE_CATEGORIES[$driver->vehicle_category],
                'phone' => $driver->phone,
                'is_online' => (bool) $driver->driverAvailability?->is_online,
                'district' => $driver->riderBaseOperation?->district?->name,
                'transport_categories' => $driver->transportCategories
                    ->map(fn (TransportCategory $category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ])
                    ->all(),
            ]),
            'meta' => [
                'categories' => TransportCategory::query()
                    ->active()
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }
}
