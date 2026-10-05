<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ListingLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingModerationController extends Controller
{
    public function index(): View
    {
        return view('admin/listings/index', [
            'listings' => Product::query()
                ->where('status', Product::STATUS_PENDING)
                ->with('vendor')
                ->latest('id')
                ->paginate(30),
        ]);
    }

    public function approve(Request $request, Product $product): RedirectResponse
    {
        app(ListingLifecycleService::class)->approve($product);

        return redirect()
            ->route('admin.listings.index')
            ->with('status', 'Listing approved and published.');
    }

    public function reject(Request $request, Product $product): RedirectResponse
    {
        app(ListingLifecycleService::class)->reject($product);

        return redirect()
            ->route('admin.listings.index')
            ->with('status', 'Listing rejected and kept unpublished.');
    }
}
