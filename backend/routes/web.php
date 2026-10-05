<?php

use App\Http\Controllers\Web\Admin\AuthController;
use App\Http\Controllers\Web\Admin\CollectorController as AdminCollectorController;
use App\Http\Controllers\Web\Admin\DataRequestController;
use App\Http\Controllers\Web\Admin\DonationSettingsController;
use App\Http\Controllers\Web\Admin\ListingModerationController;
use App\Http\Controllers\Web\Admin\LocalityController;
use App\Http\Controllers\Web\Admin\PasswordController as AdminPasswordController;
use App\Http\Controllers\Web\Admin\PostController as AdminPostController;
use App\Http\Controllers\Web\Admin\SkillCategoryController;
use App\Http\Controllers\Web\Admin\TransportCategoryController;
use App\Http\Controllers\Web\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Web\Admin\VerificationFeeSettingsController;
use App\Http\Controllers\Web\Admin\VolunteerController as AdminVolunteerController;
use App\Http\Controllers\Web\BookingController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\CollectionController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DonationController;
use App\Http\Controllers\Web\DriverBaseController;
use App\Http\Controllers\Web\DriverProfileController;
use App\Http\Controllers\Web\EmailVerificationController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ListingController;
use App\Http\Controllers\Web\MemberAuthController;
use App\Http\Controllers\Web\MemberRegistrationController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PostController;
use App\Http\Controllers\Web\ReferralController;
use App\Http\Controllers\Web\ReferralLandingController;
use App\Http\Controllers\Web\ResellerProduceController;
use App\Http\Controllers\Web\StayController;
use App\Http\Controllers\Web\TransportController;
use App\Http\Controllers\Web\VendorBookingController;
use App\Http\Controllers\Web\VendorController;
use App\Http\Controllers\Web\VendorListingController;
use App\Http\Controllers\Web\VendorProfileController;
use App\Http\Controllers\Web\VendorReportController;
use App\Http\Controllers\Web\WorkerController;
use App\Http\Controllers\Web\WorkerProfileController;
use Illuminate\Support\Facades\Route;

// Public website: browse-only, no registration required (Q3 decided).
// Guest booking from the website is the M3.1 tracer bullet.
Route::get('/', HomeController::class)->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/disclaimer', [PageController::class, 'disclaimer'])->name('disclaimer');
Route::get('/catalog', CatalogController::class)->name('catalog');
Route::get('/stays', [StayController::class, 'index'])->name('stays');
Route::get('/reseller-produce', [ResellerProduceController::class, 'index'])->name('reseller.produce');
Route::get('/workers', [WorkerController::class, 'index'])->name('workers');
Route::get('/transport', [TransportController::class, 'index'])->name('transport');
Route::get('/blog', [PostController::class, 'index'])->name('blog');
Route::get('/blog/{post}', [PostController::class, 'show'])->name('blog.show');
Route::get('/donation', [DonationController::class, 'show'])->name('donation');
Route::get('/listings/{product}', [ListingController::class, 'show'])->name('listing.show');
Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendor.show');
Route::post('/listings/{product}/book', [BookingController::class, 'store'])
    ->name('booking.store')
    ->middleware('throttle:10,1');
Route::get('/bookings/{code}', [BookingController::class, 'success'])->name('booking.success');

// Referral landing page (M6.3): /ref/{code} attributes signups.
Route::get('/ref/{code}', [ReferralLandingController::class, 'show'])->name('referral.show');
Route::post('/ref/attribute', [ReferralLandingController::class, 'attribute'])
    ->name('referral.attribute')
    ->middleware('throttle:10,1');

// Member area (M8.6): website sign-in/up for the four registerable roles.
// Session + CSRF, same role rules as the app registration (shared service).
Route::middleware('guest')->group(function () {
    Route::get('/register', [MemberRegistrationController::class, 'showRegister'])->name('register');
    Route::post('/register', [MemberRegistrationController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.attempt');
    Route::get('/login', [MemberAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [MemberAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::post('/logout', [MemberAuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::post('/dashboard/availability', [DashboardController::class, 'setAvailability'])
        ->name('dashboard.availability');
    // Vendor listing management on the website (M12.1) — same rules as the API.
    Route::get('/dashboard/listings/new', [VendorListingController::class, 'create'])
        ->name('vendor.listings.create');
    Route::post('/dashboard/listings', [VendorListingController::class, 'store'])
        ->name('vendor.listings.store');
    Route::get('/dashboard/listings/{product}/edit', [VendorListingController::class, 'edit'])
        ->name('vendor.listings.edit');
    Route::put('/dashboard/listings/{product}', [VendorListingController::class, 'update'])
        ->name('vendor.listings.update');
    Route::put('/dashboard/listings/{product}/status', [VendorListingController::class, 'status'])
        ->name('vendor.listings.status');
    Route::post('/dashboard/listings/{product}/renew', [VendorListingController::class, 'renew'])
        ->middleware('throttle:30,1')->name('vendor.listings.renew');
    Route::delete('/dashboard/listings/{product}', [VendorListingController::class, 'destroy'])
        ->name('vendor.listings.destroy');

    // Driver base of operation on the website (M15.2) — same rules as the API.
    Route::put('/dashboard/driver/base', [DriverBaseController::class, 'update'])
        ->name('dashboard.driver.base');
    Route::put('/dashboard/driver/profile', [DriverProfileController::class, 'update'])
        ->name('dashboard.driver.profile');

    // Member profile forms (M12.3 vendor, M15.3 skilled worker).
    Route::put('/dashboard/vendor/profile', [VendorProfileController::class, 'update'])
        ->name('dashboard.vendor.profile');
    Route::put('/dashboard/worker/profile', [WorkerProfileController::class, 'update'])
        ->name('dashboard.worker.profile');

    // Vendor bookings on the website (M12.4) — same lifecycle service as the app.
    Route::get('/dashboard/bookings', [VendorBookingController::class, 'index'])
        ->name('dashboard.bookings');
    Route::post('/dashboard/bookings/{booking}/status', [VendorBookingController::class, 'updateStatus'])
        ->name('dashboard.bookings.status');

    // Sales report PDF (M21.1) and affiliate codes (M21.2).
    Route::get('/dashboard/reports/sales', [VendorReportController::class, 'sales'])
        ->name('dashboard.reports.sales');
    Route::post('/dashboard/referrals', [ReferralController::class, 'store'])
        ->name('dashboard.referrals.store');
    Route::post('/dashboard/referrals/conversions/{event}/approve', [ReferralController::class, 'approve'])
        ->name('dashboard.referrals.approve');
    Route::post('/dashboard/referrals/conversions/{event}/reject', [ReferralController::class, 'reject'])
        ->name('dashboard.referrals.reject');

    // Vendor: request a farm-produce collection to a hub (M28.5).
    Route::post('/dashboard/collections', [CollectionController::class, 'store'])
        ->name('dashboard.collections.store');
});

// Admin dashboard (M4.1/M6.2/M7.3). Session sign-in, then a role check: only
// the admin role reaches these screens.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/password', [AdminPasswordController::class, 'edit'])->name('password.edit');
        Route::post('/password/request', [AdminPasswordController::class, 'requestOtp'])
            ->middleware('throttle:3,10')
            ->name('password.request');
        Route::post('/password/confirm', [AdminPasswordController::class, 'confirm'])
            ->middleware('throttle:5,1')
            ->name('password.confirm');

        Route::get('/listings', [ListingModerationController::class, 'index'])->name('listings.index');
        Route::post('/listings/{product}/approve', [ListingModerationController::class, 'approve'])->name('listings.approve');
        Route::post('/listings/{product}/reject', [ListingModerationController::class, 'reject'])->name('listings.reject');

        // Donation settings (M6.2).
        Route::get('/donation', [DonationSettingsController::class, 'edit'])->name('donation.edit');
        Route::put('/donation', [DonationSettingsController::class, 'update'])->name('donation.update');

        // Verification fee (M5.4): admin-set, paid to the volunteer directly.
        Route::get('/verification-fee', [VerificationFeeSettingsController::class, 'edit'])->name('verification-fee.edit');
        Route::put('/verification-fee', [VerificationFeeSettingsController::class, 'update'])->name('verification-fee.update');

        // Districts and localities (M4.1).
        Route::get('/localities', [LocalityController::class, 'index'])->name('localities.index');
        Route::post('/districts', [LocalityController::class, 'storeDistrict'])->name('districts.store');
        Route::put('/districts/{district}', [LocalityController::class, 'updateDistrict'])->name('districts.update');
        Route::post('/districts/{district}/localities', [LocalityController::class, 'storeLocality'])->name('localities.store');
        Route::put('/localities/{locality}', [LocalityController::class, 'updateLocality'])->name('localities.update');

        // Verification review queue (M9.5): same rules as the API.
        Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
        Route::post('/verifications/{verification}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{verification}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject');

        // Volunteer approval queue (M17.1).
        Route::get('/volunteers', [AdminVolunteerController::class, 'index'])->name('volunteers.index');
        Route::post('/volunteers/{volunteer}/approve', [AdminVolunteerController::class, 'approve'])->name('volunteers.approve');
        Route::post('/volunteers/{volunteer}/reject', [AdminVolunteerController::class, 'reject'])->name('volunteers.reject');

        // Skill categories (M17.2).
        Route::get('/skills', [SkillCategoryController::class, 'index'])->name('skills.index');
        Route::post('/skills', [SkillCategoryController::class, 'store'])->name('skills.store');
        Route::put('/skills/{skillCategory}', [SkillCategoryController::class, 'update'])->name('skills.update');

        // Collector signing (M28.1).
        Route::get('/collectors', [AdminCollectorController::class, 'index'])->name('collectors.index');
        Route::post('/collectors/assign', [AdminCollectorController::class, 'assign'])->name('collectors.assign');
        Route::post('/collectors/{assignment}/revoke', [AdminCollectorController::class, 'revoke'])->name('collectors.revoke');

        // Blog / community stories (M22.1).
        Route::get('/posts', [AdminPostController::class, 'index'])->name('posts.index');
        Route::get('/posts/create', [AdminPostController::class, 'create'])->name('posts.create');
        Route::post('/posts', [AdminPostController::class, 'store'])->name('posts.store');
        Route::get('/posts/{post}/edit', [AdminPostController::class, 'edit'])->name('posts.edit');
        Route::put('/posts/{post}', [AdminPostController::class, 'update'])->name('posts.update');
        Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.destroy');

        // Transport & errand categories (M18.1).
        Route::get('/transport', [TransportCategoryController::class, 'index'])->name('transport.index');
        Route::post('/transport', [TransportCategoryController::class, 'store'])->name('transport.store');
        Route::put('/transport/{transportCategory}', [TransportCategoryController::class, 'update'])->name('transport.update');

        // DPDP request ledger (M7.3).
        Route::get('/data-requests', [DataRequestController::class, 'index'])->name('data-requests.index');
        Route::post('/data-requests/{dataRequest}/process', [DataRequestController::class, 'process'])->name('data-requests.process');
        Route::post('/data-requests/{dataRequest}/reject', [DataRequestController::class, 'reject'])->name('data-requests.reject');
    });
});
