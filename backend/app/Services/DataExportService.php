<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\DataRequest;
use App\Models\DeviceToken;
use App\Models\Media;
use App\Models\PasswordChangeOtp;
use App\Models\Post;
use App\Models\Product;
use App\Models\Referral;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Private, portable account export; ownership is shared with deletion. */
class DataExportService
{
    public function export(User $user): string
    {
        return DB::transaction(function () use ($user) {
            // Serialize the snapshot/file write with account deactivation so
            // an in-flight export cannot recreate personal files after erasure.
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($account->is_active, 403, 'This account is no longer active.');

            return $this->writeExport($account);
        });
    }

    private function writeExport(User $user): string
    {
        $scope = new AccountDataScope($user);
        $verificationIds = array_unique([...$scope->authoredVerificationIds, ...$scope->subjectVerificationIds]);
        $base = $user->riderBaseOperation;
        $worker = $user->workerProfile;
        $data = [
            'user' => [...$user->profile(), 'vendor' => $user->vendor?->toArray()],
            'profiles' => [
                'vendor' => $user->vendor?->toArray(),
                'worker' => $worker ? [...$worker->toArray(), 'skill_category_ids' => $worker->skillCategories()->pluck('skill_categories.id')->all()] : null,
                'volunteer' => $user->verificationVolunteer?->only(['id', 'verification_status', 'availability', 'tada_notes', 'photo_path', 'reviewed_at', 'created_at', 'updated_at']),
                'driver_base' => $base ? [...$base->toArray(), 'locality_ids' => $base->localities()->pluck('localities.id')->all()] : null,
                'driver_availability' => $user->driverAvailability?->toArray(),
                'transport_category_ids' => $user->transportCategories()->pluck('transport_categories.id')->all(),
                'collector_assignment' => $user->collectorAssignment?->only(['id', 'locality_id', 'is_active', 'assigned_at', 'created_at', 'updated_at']),
            ],
            'listings' => Product::query()->whereIn('id', $scope->productIds)->get()->toArray(),
            'bookings' => $scope->bookings()->with('items')->get()->map(fn ($booking) => $booking->only([
                'id', 'code', 'vendor_id', 'status', 'completed_at', 'referral_code', 'is_reseller', 'settled_offline', 'items', 'created_at', 'updated_at',
            ]))->all(),
            'errands' => $scope->errands()->get()->map(function ($errand) use ($user) {
                if ((int) $errand->customer_id === (int) $user->id) {
                    return $errand->makeHidden(['contact_phone_index'])->toArray();
                }

                return $errand->only(['id', 'code', 'driver_id', 'status', 'created_at', 'updated_at']);
            })->all(),
            'logistics_jobs' => $scope->jobs()->get()->map(function ($job) use ($scope) {
                if (in_array($job->vendor_id, $scope->vendorIds, true)) {
                    return $job->toArray();
                }

                return $job->only(['id', 'type', 'driver_id', 'collector_id', 'status', 'assigned_at', 'completed_at', 'fee_inr', 'created_at', 'updated_at']);
            })->all(),
            'verifications' => Verification::query()->whereIn('id', $verificationIds)->get()
                ->map(fn ($report) => $report->makeHidden(['volunteer_id', 'reviewed_by'])->toArray())->all(),
            'referrals' => Referral::query()->whereIn('id', $scope->referralIds)->get()->toArray(),
            'referral_events' => $scope->referralEvents()->get()->map(function ($event) use ($user) {
                $row = $event->only(['id', 'referral_id', 'type', 'status', 'order_value', 'amount_inr', 'approved_at', 'created_at', 'updated_at']);
                $row['attributed_to_you'] = (int) $event->attributed_user_id === (int) $user->id;
                $row['approved_by_you'] = (int) $event->approved_by === (int) $user->id;

                return $row;
            })->all(),
            'posts' => Post::query()->where(fn ($q) => $q->where('author_id', $user->id)->orWhereIn('vendor_id', $scope->vendorIds))
                ->get()->map(fn ($post) => $post->makeHidden(['author_id'])->toArray())->all(),
            'badges' => Badge::query()->where(function ($q) use ($scope) {
                $q->where(fn ($q) => $q->where('subject_type', Vendor::class)->whereIn('subject_id', $scope->vendorIds))
                    ->orWhere(fn ($q) => $q->where('subject_type', Product::class)->whereIn('subject_id', $scope->productIds))
                    ->orWhereIn('post_id', Post::query()->where('author_id', $scope->user->id)->select('id'));
            })->get()->toArray(),
            'media' => Media::query()->where('uploaded_by', $user->id)->get()
                ->concat(array_map(fn ($path) => new Media(['disk' => 'public', 'path' => $path, 'uploaded_by' => $user->id]), $scope->legacyStoryFiles()))
                ->map(function (Media $media) {
                    $disk = Storage::disk($media->disk);

                    return [...$media->toArray(), 'contents_base64' => $disk->exists($media->path) ? base64_encode($disk->get($media->path)) : null];
                })->all(),
            'consents' => $scope->consents()->get()->toArray(),
            'notifications' => $user->notifications()->get()->toArray(),
            'devices' => DeviceToken::query()->where('user_id', $user->id)->get()
                ->map(fn ($device) => $device->only(['id', 'platform', 'created_at', 'updated_at']))->all(),
            'access_tokens' => $user->tokens()->get()->map(fn ($token) => $token->only([
                'id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'updated_at',
            ]))->all(),
            'sessions' => DB::table('sessions')->where('user_id', $user->id)
                ->get(['ip_address', 'user_agent', 'last_activity'])->toArray(),
            'password_changes' => PasswordChangeOtp::query()->where('user_id', $user->id)->get()
                ->map(fn ($otp) => $otp->only(['id', 'attempts', 'expires_at', 'consumed_at', 'created_at', 'updated_at']))->all(),
            'data_requests' => DataRequest::query()->where('user_id', $user->id)->get()
                ->map(fn ($request) => $request->only(['id', 'type', 'status', 'requested_at', 'processed_at']))->all(),
            'exported_at' => now()->toIso8601String(),
            'format_version' => '2.0',
        ];

        $filename = 'exports/'.Str::uuid().'.json';
        Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $filename;
    }
}
