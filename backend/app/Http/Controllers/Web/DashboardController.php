<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\DriverAvailability;
use App\Models\LogisticsJob;
use App\Models\Product;
use App\Models\SkillCategory;
use App\Models\TransportCategory;
use App\Models\User;
use App\Models\Verification;
use App\Services\ReferralService;
use App\Services\VendorSalesReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Member dashboard (M8.6). One /dashboard route, rendered per role with real
 * data. Driver online/offline writes the same `driver_availability` row the
 * app (M4.6) and the skilled-worker queue use.
 */
class DashboardController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            User::ROLE_VENDOR => $this->vendorView($user),
            User::ROLE_VOLUNTEER => view('dashboard.index', [
                'role' => $user->role,
                'user' => $user,
                'volunteer' => $user->verificationVolunteer,
                'verifications' => Verification::query()
                    ->where('volunteer_id', $user->verificationVolunteer?->id ?? 0)
                    ->latest('id')
                    ->limit(20)
                    ->get(),
            ]),
            User::ROLE_DRIVER => view('dashboard.index', [
                'role' => $user->role,
                'user' => $user,
                'availability' => $user->driverAvailability,
                'base' => $user->riderBaseOperation?->load('localities'),
                'transportCategories' => TransportCategory::query()
                    ->active()
                    ->orderBy('name')
                    ->get(),
                'districts' => District::query()
                    ->where('is_active', true)
                    ->with(['localities' => fn ($query) => $query
                        ->where('is_active', true)
                        ->orderBy('name')])
                    ->orderBy('name')
                    ->get(),
                'referrals' => app(ReferralService::class)->statsForOwner($user),
            ]),
            User::ROLE_SKILLED_WORKER => view('dashboard.index', [
                'role' => $user->role,
                'user' => $user,
                'workerProfile' => $user->workerProfile?->load('skillCategories'),
                'skillCategories' => SkillCategory::query()
                    ->active()
                    ->orderBy('name')
                    ->get(),
            ]),
            default => view('dashboard.index', [
                'role' => $user->role,
                'user' => $user,
            ]),
        };
    }

    /**
     * Vendor dashboard, including farm-produce collections (M28.5): a vendor
     * can ask for a bulk listing to be collected to a hub district.
     */
    private function vendorView(User $user): View
    {
        $products = Product::query()
            ->where('vendor_id', $user->vendor->id)
            ->latest('id')
            ->get();

        return view('dashboard.index', [
            'role' => $user->role,
            'user' => $user,
            'vendor' => $user->vendor,
            'products' => $products,
            'bookingsCount' => $user->vendor->bookings()->count(),
            'sales' => app(VendorSalesReport::class)->forVendor($user->vendor),
            'referrals' => app(ReferralService::class)->statsForOwner($user),
            'farmListings' => $products
                ->where('category', Product::CATEGORY_FARM_RESELLER)
                ->where('status', Product::STATUS_ACTIVE),
            'hubs' => District::query()->where('is_hub', true)->orderBy('name')->get(),
            'collections' => LogisticsJob::query()
                ->where('vendor_id', $user->vendor->id)
                ->where('type', LogisticsJob::TYPE_COLLECT_PRODUCE)
                ->with(['locality.district', 'destinationDistrict'])
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Driver online/offline toggle straight from the website.
     */
    public function setAvailability(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_DRIVER) {
            throw ValidationException::withMessages([
                'role' => ['Only drivers can toggle availability.'],
            ]);
        }

        $data = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        DriverAvailability::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'is_online' => (bool) $data['is_online'],
                'last_online_at' => $data['is_online'] ? null : now(),
            ],
        );

        return redirect()->route('dashboard');
    }
}
