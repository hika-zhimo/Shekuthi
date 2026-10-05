<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Booking;
use App\Models\CollectorAssignment;
use App\Models\DataRequest;
use App\Models\DeviceToken;
use App\Models\Errand;
use App\Models\Media;
use App\Models\PasswordChangeOtp;
use App\Models\Post;
use App\Models\Product;
use App\Models\Referral;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Verification;
use App\Models\VerificationVolunteer;
use App\Support\BlindIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Idempotent erasure of account data; preserved ledgers contain no free text. */
class DataDeletionService
{
    public function delete(User $user, DataRequest $request): void
    {
        $scope = new AccountDataScope($user);
        $oldEmail = $user->email;
        // Fail closed even if a later file-storage operation needs an admin retry.
        DB::transaction(function () use ($user) {
            $user->forceFill(['is_active' => false, 'remember_token' => null])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DeviceToken::query()->where('user_id', $user->id)->delete();
        });

        DB::transaction(function () use ($user, $request, $scope, $oldEmail) {
            // File I/O is not transactional. On failure leave the request open;
            // retained media/export rows make retries safe and discoverable.
            $media = Media::query()->where('uploaded_by', $user->id)->get()
                ->concat(array_map(fn ($path) => new Media(['disk' => 'public', 'path' => $path]), $scope->legacyStoryFiles()));
            $paths = $media->pluck('path')->all();
            foreach ($media as $file) {
                $this->removeFile($file->disk, $file->path);
            }
            foreach (DataRequest::query()->where('user_id', $user->id)->where('type', DataRequest::TYPE_EXPORT)->get() as $export) {
                $notes = (string) $export->notes;
                if (str_starts_with($notes, 'private:exports/')) {
                    $this->removeFile('local', substr($notes, strlen('private:')));
                } elseif (str_starts_with($notes, 'exports/')) {
                    $this->removeFile('public', $notes);
                }
            }

            // Include superseded/unreferenced export generations left by older
            // versions or an interrupted request; match the embedded owner ID.
            foreach (['local', 'public'] as $diskName) {
                $disk = Storage::disk($diskName);
                foreach ($disk->allFiles('exports') as $path) {
                    $contents = json_decode($disk->get($path), true);
                    if (is_array($contents) && (int) ($contents['user']['id'] ?? 0) === (int) $user->id) {
                        $this->removeFile($diskName, $path);
                    }
                }
            }

            // Remove owned image paths from every surface that copied them,
            // while keeping other uploaders' images and records intact.
            if ($paths !== []) {
                Product::query()->whereNotNull('images')->each(function (Product $product) use ($paths) {
                    $images = array_values(array_diff($product->images ?? [], $paths));
                    if ($images !== ($product->images ?? [])) {
                        $product->update(['images' => $images]);
                    }
                });
                Verification::query()->whereNotNull('evidence')->each(function (Verification $report) use ($paths) {
                    $evidence = array_values(array_diff($report->evidence ?? [], $paths));
                    if ($evidence !== ($report->evidence ?? [])) {
                        $report->update(['evidence' => $evidence]);
                    }
                });
                Post::query()->where(fn ($q) => $q->whereNotNull('images')->orWhereNotNull('cover_image'))->each(function (Post $post) use ($paths) {
                    $images = array_values(array_diff($post->images ?? [], $paths));
                    if ($images !== ($post->images ?? []) || in_array($post->cover_image, $paths, true)) {
                        $post->update(['images' => $images, 'cover_image' => in_array($post->cover_image, $paths, true) ? null : $post->cover_image]);
                    }
                });
                Badge::query()->whereIn('volunteer_photo', $paths)->update(['volunteer_photo' => null]);
                VerificationVolunteer::query()->whereIn('photo_path', $paths)->update(['photo_path' => null]);
            }
            $media->each->delete();

            // Keep order/price/status integrity without publicly identifiable
            // business content. Customer contacts belong to other participants.
            Product::query()->whereIn('id', $scope->productIds)->each(fn (Product $product) => $product->update([
                'title' => 'Removed listing', 'description' => null, 'images' => [],
                'batch_code' => null, 'district_id' => null, 'locality_id' => null,
                'status' => Product::STATUS_ARCHIVED, 'unpublished_at' => now(),
            ]));
            Vendor::query()->whereIn('id', $scope->vendorIds)->update([
                'display_name' => 'Deleted Vendor', 'description' => null, 'address' => null,
                'district_id' => null, 'locality_id' => null,
            ]);
            $scope->bookings()->update(['notes' => null, 'referral_code' => null]);
            $scope->jobs()->whereIn('vendor_id', $scope->vendorIds)->update(['address' => null]);
            $scope->jobs()->whereIn('vendor_id', $scope->vendorIds)->whereNotIn('status', ['completed', 'cancelled'])
                ->update(['status' => 'cancelled']);
            $scope->jobs()->where(fn ($q) => $q->where('driver_id', $user->id)->orWhere('collector_id', $user->id))
                ->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'cancelled']);
            $scope->jobs()->where('driver_id', $user->id)->update(['driver_id' => null]);
            $scope->jobs()->where('collector_id', $user->id)->update(['collector_id' => null]);
            Errand::query()->whereIn('id', $scope->customerErrandIds)->update([
                'customer_id' => null, 'contact_name' => null, 'contact_phone' => null,
                'contact_phone_index' => null, 'description' => 'Removed after account deletion.',
                'pickup_address' => null, 'drop_address' => null, 'referral_code' => null,
            ]);
            Errand::query()->whereIn('id', $scope->customerErrandIds)->whereNotIn('status', [Errand::STATUS_COMPLETED, Errand::STATUS_CANCELLED])
                ->update(['status' => Errand::STATUS_CANCELLED]);
            Errand::query()->where('driver_id', $user->id)->whereNotIn('status', [Errand::STATUS_COMPLETED, Errand::STATUS_CANCELLED])
                ->update(['status' => Errand::STATUS_CANCELLED]);
            Errand::query()->where('driver_id', $user->id)->update(['driver_id' => null]);

            $scope->consents()->delete();
            Verification::query()->whereIn('id', array_unique([...$scope->authoredVerificationIds, ...$scope->subjectVerificationIds]))
                ->update(['notes' => 'Removed after account deletion.', 'checklist' => null,
                    'evidence' => null, 'geo_lat' => null, 'geo_lng' => null]);
            Verification::query()->where('reviewed_by', $user->id)->update(['reviewed_by' => null]);
            VerificationVolunteer::query()->where('reviewed_by', $user->id)->update(['reviewed_by' => null]);
            // Immutable badge attribution names are the explicit M5.3 exception;
            // photos and authored story content are removed, never retained.
            $postIds = Post::query()->where(fn ($q) => $q->where('author_id', $user->id)->orWhereIn('vendor_id', $scope->vendorIds))->pluck('id');
            Post::query()->whereIn('id', $postIds)->each(fn (Post $post) => $post->update([
                'title' => 'Removed story', 'slug' => 'removed-story-'.$post->id,
                'excerpt' => null, 'body' => 'This story was removed after an account-data request.',
                'cover_image' => null, 'images' => [], 'status' => Post::STATUS_DRAFT,
                'published_at' => null, 'author_id' => null, 'vendor_id' => null,
            ]));
            Badge::query()->whereIn('post_id', $postIds)->update(['volunteer_photo' => null]);
            Badge::query()->where(function ($q) use ($scope) {
                $q->where(fn ($q) => $q->where('subject_type', Vendor::class)->whereIn('subject_id', $scope->vendorIds))
                    ->orWhere(fn ($q) => $q->where('subject_type', Product::class)->whereIn('subject_id', $scope->productIds));
            })->update(['revoked_at' => now(), 'volunteer_photo' => null]);

            // Unlink referral identities/codes and free-form metadata but keep
            // approved offline amounts/statuses as an anonymized ledger.
            $codes = Referral::query()->whereIn('id', $scope->referralIds)->pluck('code');
            Booking::query()->whereIn('referral_code', $codes)->update(['referral_code' => null]);
            Errand::query()->whereIn('referral_code', $codes)->update(['referral_code' => null]);
            $scope->referralEvents()->update(['attributed_user_id' => null, 'approved_by' => null, 'metadata' => null]);
            Referral::query()->whereIn('id', $scope->referralIds)->each(fn (Referral $referral) => $referral->update([
                'code' => 'deleted-referral-'.$referral->id, 'owner_user_id' => null, 'vendor_id' => null,
            ]));

            if ($worker = $user->workerProfile) {
                $worker->skillCategories()->detach();
                $worker->delete();
            }
            $user->transportCategories()->detach();
            if ($base = $user->riderBaseOperation) {
                $base->localities()->detach();
                $base->delete();
            }
            $user->driverAvailability()?->delete();
            $user->collectorAssignment()?->delete();
            CollectorAssignment::query()->where('assigned_by', $user->id)->update(['assigned_by' => null]);
            VerificationVolunteer::query()->whereIn('id', $scope->volunteerIds)->delete();
            PasswordChangeOtp::query()->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            $user->notifications()->delete();
            DataRequest::query()->where('user_id', $user->id)->update(['notes' => null]);
            $user->forceFill([
                'name' => 'Deleted User', 'email' => 'deleted-'.$user->id.'@deleted.local',
                'email_index' => BlindIndex::make('deleted-'.$user->id.'@deleted.local'),
                'phone' => null, 'phone_index' => null, 'district_id' => null, 'vehicle_category' => null,
                'email_verified_at' => null, 'password' => bcrypt(Str::random(64)),
            ])->save();
            $request->update(['status' => DataRequest::STATUS_COMPLETED, 'processed_at' => now(), 'notes' => null]);
        });
    }

    private function removeFile(string $diskName, string $path): void
    {
        $disk = Storage::disk($diskName);
        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('Account file removal failed; retry the deletion request.');
        }
    }
}
