<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Consent;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\BlindIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Booking lifecycle rules live here and only here (M3). New statuses or
 * transition rules change this class - never the controllers.
 *
 * Money boundary (Q1 option A): settlement happens offline between the
 * parties. The platform never touches money and only tracks fulfilment.
 */
class BookingService
{
    /** Allowed forward transitions. */
    public const TRANSITIONS = [
        Booking::STATUS_PENDING => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
        Booking::STATUS_CONFIRMED => [Booking::STATUS_PICKED_UP, Booking::STATUS_CANCELLED],
        Booking::STATUS_PICKED_UP => [Booking::STATUS_IN_TRANSIT],
        Booking::STATUS_IN_TRANSIT => [Booking::STATUS_DELIVERED],
        Booking::STATUS_DELIVERED => [Booking::STATUS_COMPLETED],
        Booking::STATUS_CANCELLED => [],
        Booking::STATUS_COMPLETED => [],
    ];

    public const CONSENT_TEXT_VERSION = '1.0';

    public const CONSENT_PURPOSE = 'Contact you about this booking (name and phone only).';

    /**
     * Guest booking creation (M3.1): no account, encrypted contact fields,
     * per-item MOQ validation, price snapshot at creation time.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     *
     * @throws ValidationException on any item rule failure
     */
    public function createGuestBooking(
        Vendor $vendor,
        string $contactName,
        string $contactPhone,
        array $items,
        bool $isReseller = false,
        ?string $notes = null,
        ?string $referralCode = null,
    ): Booking {
        return DB::transaction(function () use ($vendor, $contactName, $contactPhone, $items, $isReseller, $notes, $referralCode): Booking {
            $products = Product::query()
                ->where('vendor_id', $vendor->id)
                ->whereIn('id', collect($items)->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $itemErrors = $this->validateItems($items, $products);

            if ($itemErrors !== []) {
                throw ValidationException::withMessages($itemErrors);
            }

            $booking = Booking::query()->create([
                'code' => $this->generateCode(),
                'vendor_id' => $vendor->id,
                'status' => Booking::STATUS_PENDING,
                'referral_code' => $referralCode,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'contact_phone_index' => BlindIndex::make($contactPhone),
                'is_reseller' => $isReseller,
                'notes' => $notes,
                'settled_offline' => true,
            ]);

            foreach ($items as $item) {
                /** @var Product $product */
                $product = $products[$item['product_id']];

                BookingItem::query()->create([
                    'booking_id' => $booking->id,
                    'product_id' => $product->id,
                    'quantity' => (int) $item['quantity'],
                    'unit_price_snapshot' => $product->price,
                ]);
            }

            // Purpose-limited consent for the contact data (M7.1 formalizes
            // the flow and text versions; this row makes the capture real).
            Consent::query()->create([
                'subject_type' => 'booking',
                'subject_id' => $booking->id,
                'consent_key' => Consent::KEY_BOOKING_CONTACT,
                'text_version' => self::CONSENT_TEXT_VERSION,
                'purpose' => self::CONSENT_PURPOSE,
                'granted_at' => now(),
            ]);

            return $booking;
        });
    }

    /**
     * Per-item rules (Q5: per-item MOQ): the product must belong to the
     * vendor and be active; quantity must meet MOQ; stock caps the maximum
     * when set. Stock is NOT decremented here - the platform tracks
     * fulfilment only; vendors manage their own stock.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @param  Collection<int, Product>  $products
     * @return array<string, array<int, string>> field => messages
     */
    private function validateItems(array $items, Collection $products): array
    {
        $itemErrors = [];

        foreach ($items as $index => $item) {
            /** @var Product|null $product */
            $product = $products->get($item['product_id']);

            if ($product === null) {
                $itemErrors["items.{$index}.product_id"] = ['This product is not available from this seller.'];

                continue;
            }

            if (! $product->isLive()) {
                $itemErrors["items.{$index}.product_id"] = ['This listing is not open for booking right now.'];

                continue;
            }

            $quantity = (int) $item['quantity'];

            if ($quantity < $product->moq) {
                $itemErrors["items.{$index}.quantity"] = [
                    "Minimum order for {$product->title} is {$product->moq}.",
                ];

                continue;
            }

            if ($product->stock !== null && $quantity > $product->stock) {
                $itemErrors["items.{$index}.quantity"] = [
                    "Only {$product->stock} left of {$product->title}.",
                ];
            }
        }

        return $itemErrors;
    }

    /**
     * Vendor/admin status change (M3.2). Only forward transitions from
     * BookingService::TRANSITIONS are allowed.
     */
    public function changeStatus(Booking $booking, string $status): Booking
    {
        $allowed = self::TRANSITIONS[$booking->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["A {$booking->status} booking cannot move to {$status}."],
            ]);
        }

        $previousStatus = $booking->status;

        $booking->update([
            'status' => $status,
            'completed_at' => $status === Booking::STATUS_COMPLETED
                ? now()
                : $booking->completed_at,
        ]);

        // Completing a sale credits the affiliate code it was booked through
        // (M21.2). Commission is snapshotted; no money moves here.
        if ($status === Booking::STATUS_COMPLETED && $booking->referral_code) {
            app(ReferralService::class)->recordConversion(
                $booking->referral_code,
                null,
                $this->orderValue($booking),
            );
        }

        app(NotificationService::class)->bookingStatusChanged($booking, $previousStatus);

        return $booking;
    }

    /**
     * Value of a booking from its price snapshots (the affiliate basis).
     */
    private function orderValue(Booking $booking): float
    {
        return (float) $booking->items()
            ->get()
            ->sum(fn (BookingItem $item) => (float) $item->unit_price_snapshot * (int) $item->quantity);
    }

    /**
     * Guest lookup (M3.3): booking code plus the phone that made the
     * booking. The code alone is never sufficient.
     */
    public function lookupForGuest(string $code, string $phone): ?Booking
    {
        return Booking::query()
            ->with(['items.product', 'vendor'])
            ->forGuest(strtoupper(trim($code)), $phone)
            ->first();
    }

    /**
     * Guest cancel: only while still pending, and only with the booking
     * phone. Once the vendor confirmed, contact the vendor instead.
     */
    public function cancelByGuest(Booking $booking, string $phone): Booking
    {
        if (! in_array($booking->status, [Booking::STATUS_PENDING], true)) {
            throw ValidationException::withMessages([
                'code' => ['This booking can no longer be cancelled online. Contact the seller.'],
            ]);
        }

        return $this->changeStatus($booking, Booking::STATUS_CANCELLED);
    }

    private function generateCode(): string
    {
        do {
            $code = 'BK-'.strtoupper(Str::random(6));
        } while (Booking::query()->where('code', $code)->exists());

        return $code;
    }
}
