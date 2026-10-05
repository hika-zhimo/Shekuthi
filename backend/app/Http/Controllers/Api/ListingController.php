<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\ProductResource;
use App\Models\Consent;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\ListingLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor listing CRUD (M2.2). Ownership is enforced by ProductPolicy.
 * The vendor relationship is created lazily for admins testing the API.
 */
class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->vendorFor($request);

        $products = $vendor
            ? $vendor->products()->latest()->get()
            : collect();

        return response()->json([
            'data' => ProductResource::collection($products),
        ]);
    }

    public function store(StoreListingRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $vendor = $this->requireVendor($request);
        $validated = $request->safe()->except(['images', 'image_public_consent']);
        $validated['images'] = $request->validatedImages();

        $product = $vendor->products()->create(array_merge($validated, [
            'status' => Product::STATUS_PENDING,
        ]));

        if ($product->images !== []) {
            Consent::query()->create([
                'subject_type' => Product::class,
                'subject_id' => $product->id,
                'consent_key' => Consent::KEY_LISTING_IMAGES_PUBLIC,
                'text_version' => '1.0',
                'purpose' => 'The vendor agreed that listing images are publicly viewable with the listing.',
                'granted_at' => now(),
            ]);
        }

        return response()->json([
            'data' => new ProductResource($product->load('vendor')),
        ], 201);
    }

    public function update(UpdateListingRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->safe()->except(['images', 'image_public_consent']);
        if ($request->exists('images')) {
            $validated['images'] = $request->validatedImages();
        }

        $product = app(ListingLifecycleService::class)->submit($product, $validated);

        if ($product->images !== [] && $request->boolean('image_public_consent')) {
            Consent::query()->create([
                'subject_type' => Product::class,
                'subject_id' => $product->id,
                'consent_key' => Consent::KEY_LISTING_IMAGES_PUBLIC,
                'text_version' => '1.0',
                'purpose' => 'The vendor agreed that listing images are publicly viewable with the listing.',
                'granted_at' => now(),
            ]);
        }

        return response()->json([
            'data' => new ProductResource($product->fresh('vendor')),
        ]);
    }

    public function renew(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $product = app(ListingLifecycleService::class)->renew($product);

        return response()->json(['data' => new ProductResource($product->load('vendor'))]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        app(ListingLifecycleService::class)->submit($product, ['status' => Product::STATUS_ARCHIVED]);

        return response()->json([
            'message' => 'Listing archived.',
        ]);
    }

    private function vendorFor(Request $request): ?Vendor
    {
        return Vendor::query()->where('user_id', $request->user()->id)->first();
    }

    private function requireVendor(Request $request): Vendor
    {
        $vendor = $this->vendorFor($request);

        if ($vendor === null) {
            if ($request->user()->role !== 'admin') {
                abort(403, 'Only vendors can create listings.');
            }

            $vendor = Vendor::query()->create([
                'user_id' => $request->user()->id,
                'display_name' => 'Platform administration',
                'category' => Vendor::CATEGORY_TRADITIONAL,
            ]);
        }

        return $vendor;
    }
}
