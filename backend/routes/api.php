<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CollectorController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\DataDeletionController;
use App\Http\Controllers\Api\DataExportController;
use App\Http\Controllers\Api\DataExportDownloadController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DriverAvailabilityController;
use App\Http\Controllers\Api\DriverAvailabilityOverviewController;
use App\Http\Controllers\Api\DriverBaseController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ErrandController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\LocationsController;
use App\Http\Controllers\Api\LogisticsController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationInboxController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\ResellerProduceController;
use App\Http\Controllers\Api\StayController;
use App\Http\Controllers\Api\TransportDirectoryController;
use App\Http\Controllers\Api\VendorBookingController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\VerificationFeeController;
use App\Http\Controllers\Api\VerificationQuestionnaireController;
use App\Http\Controllers\Api\VolunteerController;
use App\Http\Controllers\Api\WorkerDirectoryController;
use Illuminate\Support\Facades\Route;

// JSON API for the Flutter app. Buyers never register - they browse as guests.
Route::prefix('v1')->group(function () {
    // Public: guest browsing and guest booking (no account, ever, for buyers).
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/catalog/{product}', [CatalogController::class, 'show']);
    Route::get('/stays', [StayController::class, 'index']);
    Route::get('/vendors/{vendor}', [VendorController::class, 'show']);
    Route::get('/workers', [WorkerDirectoryController::class, 'index']);
    Route::get('/transport', [TransportDirectoryController::class, 'index']);
    Route::get('/reseller-produce', [ResellerProduceController::class, 'index']);
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::post('/bookings/lookup', [BookingController::class, 'lookup'])
        ->middleware('throttle:10,1');
    Route::post('/bookings/cancel', [BookingController::class, 'cancel'])
        ->middleware('throttle:10,1');

    // Donations: public read of UPI settings (M6.1).
    Route::get('/donation', [DonationController::class, 'show']);

    // Verification fee: public read (M5.4).
    Route::get('/verification-fee', [VerificationFeeController::class, 'show']);
    Route::get('/verification-questionnaire', [VerificationQuestionnaireController::class, 'index']);

    // Errands: guest creates and looks up without an account.
    Route::post('/errands', [ErrandController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::post('/errands/lookup', [ErrandController::class, 'lookup'])
        ->middleware('throttle:10,1');

    // Public reference data for pickers (districts + localities).
    Route::get('/locations', [LocationsController::class, 'index']);

    // Driver-availability view (M4.6, Q8): online drivers per locality.
    Route::get('/drivers-online', [DriverAvailabilityOverviewController::class, 'index']);

    // Auth (rate limited).
    Route::post('/auth/register', [RegistrationController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');
    Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');
    Route::get('/exports/{dataRequest}/download', DataExportDownloadController::class)
        ->middleware(['signed', 'throttle:10,1'])
        ->name('api.exports.download');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
    });

    Route::middleware(['auth:sanctum', 'active', 'verified'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);

        // Shared image upload (products, avatars) — one validator for all media.
        Route::post('/media', [MediaController::class, 'store'])
            ->middleware('throttle:30,1');

        // Vendor listing CRUD; policy restricts to owners and admins.
        Route::get('/listings', [ListingController::class, 'index']);
        Route::post('/listings', [ListingController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::post('/listings/{product}/renew', [ListingController::class, 'renew'])->middleware('throttle:30,1');
        Route::put('/listings/{product}', [ListingController::class, 'update']);
        Route::delete('/listings/{product}', [ListingController::class, 'destroy']);

        // Vendor bookings (M3.2).
        Route::get('/vendor/bookings', [VendorBookingController::class, 'index']);
        Route::post('/vendor/bookings/{booking}/status', [VendorBookingController::class, 'updateStatus']);

        // Driver base of operation (M4.2): one district + up to 5 localities.
        Route::get('/driver/base', [DriverBaseController::class, 'show']);
        Route::post('/driver/base', [DriverBaseController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::put('/driver/base/localities', [DriverBaseController::class, 'update'])
            ->middleware('throttle:30,1');

        // Driver online/offline toggle (M4.3).
        Route::get('/driver/availability', [DriverAvailabilityController::class, 'show']);
        Route::put('/driver/availability', [DriverAvailabilityController::class, 'update'])
            ->middleware('throttle:30,1');

        // Collector sub-division + farm-produce collections (M28).
        Route::get('/collector/assignment', [CollectorController::class, 'assignment']);
        Route::post('/collections', [CollectionController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::get('/collections', [CollectionController::class, 'index']);
        Route::post('/collections/{job}/accept', [CollectionController::class, 'accept'])
            ->middleware('throttle:30,1');
        Route::post('/collections/{job}/status', [CollectionController::class, 'updateStatus'])
            ->middleware('throttle:30,1');

        // Logistics jobs: vendor creates, driver accepts/progresses (M4.4).
        Route::post('/logistics/jobs', [LogisticsController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::get('/logistics/jobs', [LogisticsController::class, 'index']);
        Route::post('/logistics/jobs/{job}/accept', [LogisticsController::class, 'accept'])
            ->middleware('throttle:30,1');
        Route::post('/logistics/jobs/{job}/status', [LogisticsController::class, 'updateStatus'])
            ->middleware('throttle:30,1');

        // Errands: driver accepts/progresses (M4.5).
        Route::get('/errands', [ErrandController::class, 'index']);
        Route::post('/errands/{errand}/accept', [ErrandController::class, 'accept'])
            ->middleware('throttle:30,1');
        Route::post('/errands/{errand}/status', [ErrandController::class, 'updateStatus'])
            ->middleware('throttle:30,1');

        // Volunteer profile + visit queue (M5.1).
        Route::get('/volunteer/profile', [VolunteerController::class, 'show']);
        Route::post('/volunteer/profile', [VolunteerController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::put('/volunteer/profile', [VolunteerController::class, 'update'])
            ->middleware('throttle:30,1');
        Route::get('/volunteer/queue', [VolunteerController::class, 'queue']);
        Route::get('/volunteer/available', [VolunteerController::class, 'available']);

        // Verification reports + badge (M5.2/M5.3).
        Route::post('/verifications', [VerificationController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::get('/verifications', [VerificationController::class, 'index']);
        Route::get('/verifications/{verification}', [VerificationController::class, 'show']);
        Route::post('/verifications/{verification}/submit', [VerificationController::class, 'submit'])
            ->middleware('throttle:30,1');
        Route::post('/verifications/{verification}/approve', [VerificationController::class, 'approve'])
            ->middleware('throttle:30,1');
        Route::post('/verifications/{verification}/reject', [VerificationController::class, 'reject'])
            ->middleware('throttle:30,1');

        // Vendor referral management (M6.3).
        Route::get('/referrals', [ReferralController::class, 'index']);
        Route::post('/referrals', [ReferralController::class, 'store'])
            ->middleware('throttle:30,1');

        // DPDP consent (M7.1).
        Route::post('/consents', [ConsentController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::get('/consents', [ConsentController::class, 'index']);
        Route::delete('/consents/{consent}', [ConsentController::class, 'destroy']);

        // DPDP data export/deletion (M7.2/M7.3).
        Route::post('/export', [DataExportController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::post('/deletion', [DataDeletionController::class, 'store'])
            ->middleware('throttle:10,1');

        // Device push tokens (M8.1).
        Route::post('/device-tokens', [DeviceTokenController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::delete('/device-tokens/{token}', [DeviceTokenController::class, 'destroy'])
            ->middleware('throttle:30,1');

        // In-app notification inbox (M8.1).
        Route::get('/notifications', [NotificationInboxController::class, 'index']);
        Route::post('/notifications/read', [NotificationInboxController::class, 'markRead'])
            ->middleware('throttle:60,1');
    });
});
