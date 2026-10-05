<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Product;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Guest booking from the website (M3.1 tracer bullet): single-product
 * booking with name + phone. Multi-item bookings use the app/API.
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function store(Request $request, Product $product)
    {
        abort_unless($product->isLive(), 404);

        $product->load('vendor');

        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100000',
            ],
            'is_reseller' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $booking = $this->bookings->createGuestBooking(
                $product->vendor,
                $data['contact_name'],
                $data['contact_phone'],
                [
                    [
                        'product_id' => $product->id,
                        'quantity' => (int) $data['quantity'],
                    ],
                ],
                (bool) ($data['is_reseller'] ?? false),
                $data['notes'] ?? null,
                // A referral code in the session (set by /ref/{code} or a
                // shared listing link) follows the guest into the booking so
                // the affiliate is credited when it completes (M21.2).
                is_string($request->session()->get('referral_code'))
                    ? (string) $request->session()->get('referral_code')
                    : null,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('booking.success', ['code' => $booking->code])
            ->with('booking_phone_last4', substr($data['contact_phone'], -4));
    }

    public function success(Request $request, string $code)
    {
        // The success page shows only the code and the last 4 phone digits
        // (kept in flash session) - not the full contact data.
        $booking = Booking::query()
            ->where('code', strtoupper($code))
            ->with(['items.product', 'vendor'])
            ->firstOrFail();

        return view('pages.booking-success', [
            'booking' => $booking,
            'phoneLast4' => (string) session('booking_phone_last4', '****'),
        ]);
    }
}
