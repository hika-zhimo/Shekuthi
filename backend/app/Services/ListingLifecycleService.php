<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Post;
use App\Models\Product;
use App\Models\Verification;
use App\Notifications\ListingExpiryNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** All publication transitions and annual retention live here. */
class ListingLifecycleService
{
    public function submit(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $requested = $data['status'] ?? Product::STATUS_PENDING;
            $data['status'] = in_array($requested, [Product::STATUS_INACTIVE, Product::STATUS_ARCHIVED], true)
                && count(array_diff(array_keys($data), ['status'])) === 0
                ? $requested : Product::STATUS_PENDING;
            $data['unpublished_at'] = $locked->unpublished_at ?? now();
            $locked->update($data);

            return $locked;
        });
    }

    public function approve(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($locked->status === Product::STATUS_PENDING, 422);
            $locked->update(array_merge($this->clearWarnings(), [
                'status' => Product::STATUS_ACTIVE, 'unpublished_at' => null,
                'approved_at' => now(), 'expires_at' => now()->addYearNoOverflow(),
                'expired_at' => null, 'deletion_scheduled_at' => null,
            ]));
        });
    }

    public function reject(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($locked->status === Product::STATUS_PENDING, 422);
            $data = ['status' => Product::STATUS_INACTIVE, 'unpublished_at' => now()];
            if ($locked->expires_at?->lte(now())) {
                $data = array_merge($data, $this->clearWarnings(), [
                    'expired_at' => now(), 'deletion_scheduled_at' => now()->addDays(30),
                ]);
            }
            $locked->update($data);
        });
    }

    public function renew(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($locked->canRenew(), 422, 'Only expired listings can be renewed.');
            $locked->update(['status' => Product::STATUS_PENDING, 'unpublished_at' => $locked->unpublished_at ?? now()]);

            return $locked;
        });
    }

    /** Returns counts; failures are retried next run without erasing a listing. */
    public function sweep(bool $dryRun = false): array
    {
        $counts = ['expired' => 0, 'deleted' => 0, 'warnings' => 0, 'failed' => 0];
        Product::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->where('status', '!=', Product::STATUS_PENDING)->select('id')->chunkById(100, function ($rows) use (&$counts, $dryRun): void {
                foreach ($rows as $row) {
                    try {
                        DB::transaction(function () use ($row, &$counts, $dryRun): void {
                            $product = Product::query()->lockForUpdate()->find($row->id);
                            if (! $product || $product->status === Product::STATUS_PENDING || $product->expires_at->gt(now())) {
                                return;
                            }
                            if ($dryRun) {
                                if ($product->expired_at === null) {
                                    $counts['expired']++;
                                    $product->deletion_scheduled_at = $product->expires_at->copy()->addDays(30);
                                }
                                $owner = $product->vendor?->user;
                                if (! $owner || ! $owner->is_active || ! $owner->email) {
                                    $counts['failed']++;

                                    return;
                                }
                                foreach (['expiry', 'reminder'] as $stage) {
                                    if ($stage === 'reminder' && $product->deletion_scheduled_at->gt(now()->addDays(7))) {
                                        continue;
                                    }
                                    foreach (['mail', 'database'] as $channel) {
                                        $counts['warnings'] += $product->{$stage.'_'.$channel.'_sent_at'} === null ? 1 : 0;
                                    }
                                }
                                $counts['deleted'] += $this->canRemove($product) ? 1 : 0;

                                return;
                            }
                            if ($product->expired_at === null) {
                                $product->update(['status' => Product::STATUS_INACTIVE,
                                    'unpublished_at' => $product->expires_at, 'expired_at' => $product->expires_at,
                                    'deletion_scheduled_at' => $product->expires_at->copy()->addDays(30)]);
                                $counts['expired']++;
                            }
                            $owner = $product->vendor?->user;
                            if (! $owner || ! $owner->is_active || ! $owner->email) {
                                $counts['failed']++;

                                return;
                            }
                            // Catch-up runs must still give seven days' notice before erasure.
                            if ($product->deletion_scheduled_at->lte(now()->addDays(7))
                                && ($product->reminder_mail_sent_at === null || $product->reminder_database_sent_at === null)) {
                                $product->update(['deletion_scheduled_at' => now()->addDays(7)]);
                            }
                            foreach (['expiry', 'reminder'] as $stage) {
                                if ($stage === 'reminder' && $product->deletion_scheduled_at->gt(now()->addDays(7))) {
                                    continue;
                                }
                                foreach (['mail', 'database'] as $channel) {
                                    $field = $stage.'_'.$channel.'_sent_at';
                                    if ($product->$field !== null) {
                                        continue;
                                    }
                                    try {
                                        Notification::sendNow($owner, new ListingExpiryNotification(
                                            $product->id, $product->title,
                                            $product->deletion_scheduled_at->copy()->utc()->toDateTimeString().' UTC', $stage === 'reminder'
                                        ), [$channel]);
                                        $product->update([$field => now()]);
                                        $counts['warnings']++;
                                    } catch (\Throwable $e) {
                                        // Do not roll back successful channels or log private mail content.
                                        Log::warning('Listing warning failed', ['listing_id' => $product->id, 'channel' => $channel, 'exception' => $e::class]);
                                        $counts['failed']++;
                                    }
                                }
                            }
                            if ($this->canRemove($product)) {
                                $this->remove($product);
                                $counts['deleted']++;
                            }
                        });
                    } catch (\Throwable $e) {
                        Log::warning('Listing lifecycle failed', ['listing_id' => $row->id, 'exception' => $e::class]);
                        $counts['failed']++;
                    }
                }
            });

        return $counts;
    }

    private function canRemove(Product $product): bool
    {
        return $product->deletion_scheduled_at?->lte(now())
            && $product->expiry_mail_sent_at !== null && $product->expiry_database_sent_at !== null
            && $product->reminder_mail_sent_at?->lte(now()->subDays(7))
            && $product->reminder_database_sent_at?->lte(now()->subDays(7));
    }

    private function remove(Product $product): void
    {
        foreach ($product->images ?? [] as $path) {
            // Shared uploads remain available to other listings; do not erase them.
            if (Product::query()->whereKeyNot($product->id)->whereJsonContains('images', $path)->exists()
                || Post::query()->whereJsonContains('images', $path)->exists()
                || Verification::query()->whereJsonContains('evidence', $path)->exists()) {
                continue;
            }
            if (! Storage::disk('public')->delete($path)) {
                throw new \RuntimeException('Listing photo removal failed.');
            }
            Media::query()->where('disk', 'public')->where('path', $path)
                ->where('uploaded_by', $product->vendor->user_id)->delete();
        }
        // Keep a minimal non-public tombstone for existing booking foreign keys.
        $product->update(['status' => Product::STATUS_ARCHIVED, 'title' => 'Deleted listing',
            'description' => null, 'images' => [], 'batch_code' => null]);
        $product->delete();
    }

    private function clearWarnings(): array
    {
        return ['expiry_mail_sent_at' => null, 'expiry_database_sent_at' => null,
            'reminder_mail_sent_at' => null, 'reminder_database_sent_at' => null];
    }
}
