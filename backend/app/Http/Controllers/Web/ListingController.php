<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Referral;
use App\Models\User;
use App\Models\VerificationFeeSetting;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    /**
     * Public listing detail (M2.5): verified-badge slot, MOQ + booking CTA,
     * share buttons carrying referral attribution (M9.3).
     */
    public function show(Request $request, Product $product)
    {
        abort_unless($product->isLive(), 404);

        // M9.3: a shared listing link may carry ?ref=CODE — remember it in
        // the session for signup attribution, exactly like /ref/{code} does.
        $ref = $request->query('ref');

        if (is_string($ref) && $ref !== ''
            && Referral::query()->where('code', $ref)->exists()) {
            $request->session()->put('referral_code', $ref);
        }

        $product->load('vendor');

        return view('pages.listing', [
            'product' => $product,
            'verificationFee' => VerificationFeeSetting::current()->amount_inr,
            'shareUrl' => $this->shareUrl($request, $product),
        ]);
    }

    /**
     * Plain listing URL for everyone; the listing's owner vendor gets their
     * latest referral code appended so shares attribute signups to them.
     */
    private function shareUrl(Request $request, Product $product): string
    {
        $url = route('listing.show', $product);

        $user = $request->user();

        if ($user !== null && $user->role === User::ROLE_VENDOR
            && $user->vendor !== null
            && $user->vendor->id === $product->vendor_id) {
            $code = Referral::query()
                ->where('vendor_id', $user->vendor->id)
                ->latest('id')
                ->value('code');

            if ($code !== null) {
                return $url.'?ref='.urlencode($code);
            }
        }

        return $url;
    }
}
