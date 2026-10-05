<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Support\BlindIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Profile read/update for registered roles (M2.1). Role-specific fields are
 * validated and saved separately: vendor details, worker services. Buyer
 * guests have no profile endpoint at all.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile();

        $profile += match ($user->role) {
            'vendor' => ['vendor' => $this->vendorProfile($user)],
            'driver' => ['driver' => $this->driverProfile($user)],
            'skilled_worker' => ['worker' => $this->workerProfile($user)],
            default => [],
        };

        return response()->json(['user' => $profile]);
    }

    /**
     * Vendor profile for the app's shop-profile screen (M9.4). Adds the
     * district name so the client can display it without a second request;
     * category and district stay read-only (set at registration).
     */
    private function vendorProfile(User $user): ?array
    {
        $vendor = $user->vendor;

        if ($vendor === null) {
            return null;
        }

        return $vendor->only(['display_name', 'category', 'district_id', 'description'])
            + ['district_name' => $vendor->district?->name];
    }

    /**
     * Skilled-worker profile (M17.2): custom work in `services`, plus the
     * canonical skill categories they ticked so the app can filter by them.
     *
     * @return array<string, mixed>|null
     */
    private function workerProfile(User $user): ?array
    {
        $profile = $user->workerProfile;

        if ($profile === null) {
            return null;
        }

        $skills = $profile->skillCategories()->orderBy('name')->get();

        return $profile->only(['services', 'service_areas']) + [
            'skill_category_ids' => $skills->pluck('id')->all(),
            'skill_categories' => $skills
                ->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->name])
                ->all(),
        ];
    }

    /**
     * Driver profile (M18.1): online state plus the transport/errand
     * categories they tick (the app's equivalent of the web work profile).
     *
     * @return array<string, mixed>
     */
    private function driverProfile(User $user): array
    {
        $categories = $user->transportCategories()->orderBy('name')->get();

        return [
            'is_online' => (bool) $user->driverAvailability?->is_online,
            'vehicle_category' => $user->vehicle_category,
            'vehicle_categories' => User::VEHICLE_CATEGORIES,
            'transport_category_ids' => $categories->pluck('id')->all(),
            'transport_categories' => $categories
                ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
                ->all(),
        ];
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            // Vendor-only fields.
            'display_name' => ['sometimes', 'string', 'max:120'],
            'vendor_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Worker-only fields.
            'services' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'service_areas' => ['sometimes', 'nullable', 'string', 'max:500'],
            'skill_category_ids' => ['sometimes', 'array'],
            'skill_category_ids.*' => [
                'integer',
                Rule::exists('skill_categories', 'id')->where('is_active', true),
            ],
            // Driver-only fields.
            'vehicle_category' => [
                Rule::prohibitedIf($user->role !== User::ROLE_DRIVER),
                Rule::requiredIf($user->role === User::ROLE_DRIVER && $request->exists('transport_category_ids')),
                'string', Rule::in(array_keys(User::VEHICLE_CATEGORIES)),
            ],
            'transport_category_ids' => ['sometimes', 'array'],
            'transport_category_ids.*' => [
                'integer',
                Rule::exists('transport_categories', 'id')->where('is_active', true),
            ],
        ]);

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
            $user->phone_index = $data['phone'] !== null
                ? BlindIndex::make($data['phone'])
                : null;
        }

        if ($user->role === User::ROLE_DRIVER && array_key_exists('vehicle_category', $data)) {
            $user->vehicle_category = $data['vehicle_category'];
        }

        $user->save();

        if ($user->role === 'vendor') {
            $vendor = $user->vendor;

            if ($vendor !== null) {
                $vendor->fill([
                    'display_name' => $data['display_name'] ?? $vendor->display_name,
                    'description' => $data['vendor_description'] ?? $vendor->description,
                ])->save();
            }
        }

        if ($user->role === 'skilled_worker') {
            $profile = WorkerProfile::query()->firstOrCreate(['user_id' => $user->id]);
            $profile->fill(array_intersect_key($data, array_flip(['services', 'service_areas'])))->save();

            if (array_key_exists('skill_category_ids', $data)) {
                $profile->skillCategories()->sync($data['skill_category_ids'] ?? []);
            }
        }

        if ($user->role === 'driver' && array_key_exists('transport_category_ids', $data)) {
            $user->transportCategories()->sync($data['transport_category_ids'] ?? []);
        }

        return response()->json(['user' => $this->show($request)->getData(true)['user']]);
    }
}
