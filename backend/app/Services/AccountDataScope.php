<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Consent;
use App\Models\Errand;
use App\Models\LogisticsJob;
use App\Models\Media;
use App\Models\Post;
use App\Models\Product;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Verification;
use App\Models\VerificationVolunteer;
use Illuminate\Database\Eloquent\Builder;

/** Registered-account ownership boundaries shared by data-rights operations. */
class AccountDataScope
{
    public readonly array $vendorIds;

    public readonly array $productIds;

    public readonly array $volunteerIds;

    public readonly array $authoredVerificationIds;

    public readonly array $subjectVerificationIds;

    public readonly array $referralIds;

    public readonly array $customerErrandIds;

    public function __construct(public readonly User $user)
    {
        $this->vendorIds = Vendor::query()->where('user_id', $user->id)->pluck('id')->all();
        $this->productIds = Product::query()->whereIn('vendor_id', $this->vendorIds)->pluck('id')->all();
        $this->volunteerIds = VerificationVolunteer::query()->where('user_id', $user->id)->pluck('id')->all();
        $this->authoredVerificationIds = Verification::query()->whereIn('volunteer_id', $this->volunteerIds)->pluck('id')->all();
        $this->subjectVerificationIds = Verification::query()->where(function (Builder $query) {
            $query->where(fn (Builder $q) => $q->where('subject_type', Vendor::class)->whereIn('subject_id', $this->vendorIds))
                ->orWhere(fn (Builder $q) => $q->where('subject_type', Product::class)->whereIn('subject_id', $this->productIds));
        })->pluck('id')->all();
        $this->referralIds = Referral::query()->where(function (Builder $query) {
            $query->where('owner_user_id', $this->user->id)->orWhereIn('vendor_id', $this->vendorIds);
        })->pluck('id')->all();
        $this->customerErrandIds = Errand::query()->where('customer_id', $user->id)->pluck('id')->all();
    }

    public function consents(): Builder
    {
        return Consent::query()->where(function (Builder $query) {
            $subjects = [
                User::class => [$this->user->id],
                Product::class => $this->productIds,
                Verification::class => $this->authoredVerificationIds,
                Errand::class => $this->customerErrandIds,
            ];
            foreach ($subjects as $type => $ids) {
                $query->orWhere(fn (Builder $q) => $q->where('subject_type', $type)->whereIn('subject_id', $ids));
            }
        });
    }

    /** Older admin story covers predate media rows but have an author owner. */
    public function legacyStoryFiles(): array
    {
        $paths = Post::query()->where('author_id', $this->user->id)->get()
            ->flatMap(fn (Post $post) => [$post->cover_image, ...($post->images ?? [])])
            ->filter(fn ($path) => is_string($path) && str_starts_with($path, 'blog/'))
            ->unique()->values()->all();
        $recorded = Media::query()->whereIn('path', $paths)->pluck('path')->all();

        return array_values(array_diff($paths, $recorded));
    }

    public function bookings(): Builder
    {
        // Guest phone numbers are not a verified link to an account.
        return Booking::query()->whereIn('vendor_id', $this->vendorIds);
    }

    public function errands(): Builder
    {
        return Errand::query()->where(fn (Builder $q) => $q->where('customer_id', $this->user->id)
            ->orWhere('driver_id', $this->user->id));
    }

    public function jobs(): Builder
    {
        return LogisticsJob::query()->where(fn (Builder $q) => $q->whereIn('vendor_id', $this->vendorIds)
            ->orWhere('driver_id', $this->user->id)->orWhere('collector_id', $this->user->id));
    }

    public function referralEvents(): Builder
    {
        return ReferralEvent::query()->where(fn (Builder $q) => $q->whereIn('referral_id', $this->referralIds)
            ->orWhere('attributed_user_id', $this->user->id)->orWhere('approved_by', $this->user->id));
    }
}
