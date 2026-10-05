<?php

namespace App\Http\Resources;

use App\Models\VerificationFeeSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public product shape for the app and website. Image URLs are resolved from
 * the public disk; the verified flag is denormalized for badge display (M5.3).
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Catalog endpoints eager-load badges; other callers retain lazy lookup.
        $badge = $this->verified_badge;
        $fee = null;
        if ($badge === null) {
            // Request-local reuse avoids stale fees and cross-request state.
            $key = self::class.'.verification_fee';
            if (! $request->attributes->has($key)) {
                $request->attributes->set($key, VerificationFeeSetting::current()->amount_inr);
            }
            $fee = $request->attributes->get($key);
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'price' => $this->price !== null ? (float) $this->price : null,
            'unit' => $this->unit,
            'moq' => $this->moq,
            'stock' => $this->stock,
            'available_from' => $this->available_from?->toDateString(),
            'available_to' => $this->available_to?->toDateString(),
            'images' => collect($this->images ?? [])
                ->map(fn (string $path): string => \Storage::disk('public')->url($path))
                ->values()
                ->all(),
            'district_id' => $this->district_id,
            'locality_id' => $this->locality_id,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'deletion_scheduled_at' => $this->deletion_scheduled_at?->toIso8601String(),
            'can_renew' => $this->canRenew(),
            'is_verified' => $badge !== null,
            'verified_by' => $badge?->volunteer_name,
            'verification_fee_inr' => $this->when(
                $badge === null && $fee !== null,
                fn () => (float) $fee
            ),
            'vendor' => $this->whenLoaded('vendor', fn (): ?array => $this->vendor === null ? null : [
                'id' => $this->vendor->id,
                'display_name' => $this->vendor->display_name,
                'category' => $this->vendor->category,
                'district_id' => $this->vendor->district_id,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
