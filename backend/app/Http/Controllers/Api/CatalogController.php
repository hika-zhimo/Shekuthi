<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest catalog for the app (M2.4): browse, search, filter. No auth.
 * Search covers title and description only — never PII.
 */
class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'in:'.implode(',', Product::CATEGORIES)],
            'district_id' => ['nullable', 'integer'],
            'locality_id' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Product::query()
            ->active()
            ->with(['vendor', 'activeBadge'])
            ->latest();

        if (isset($data['q'])) {
            $term = $data['q'];
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $query
            ->when(isset($data['category']), fn ($q) => $q->where('category', $data['category']))
            ->when(isset($data['district_id']), fn ($q) => $q->where('district_id', $data['district_id']))
            ->when(isset($data['locality_id']), fn ($q) => $q->where('locality_id', $data['locality_id']))
            ->when(isset($data['min_price']), fn ($q) => $q->where('price', '>=', $data['min_price']))
            ->when(isset($data['max_price']), fn ($q) => $q->where('price', '<=', $data['max_price']));

        $products = $query->paginate($data['per_page'] ?? 24);

        return response()->json([
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->isLive(), 404);

        $product->load(['vendor', 'activeBadge']);

        return response()->json([
            'data' => new ProductResource($product),
            'share_url' => $this->shareUrlFor($product),
        ]);
    }

    /**
     * Shareable website link for the app's Share button (M9.3). Computed
     * server-side so the owner's referral code is attached in exactly one
     * place (mirrors Web\ListingController::show): guests get the plain
     * URL, the owner vendor gets ?ref=CODE.
     */
    private function shareUrlFor(Product $product): string
    {
        $url = route('listing.show', $product);

        $user = auth('sanctum')->user();

        $vendor = $user?->vendor;

        if ($vendor !== null && $vendor->id === $product->vendor_id) {
            $code = Referral::query()
                ->where('vendor_id', $vendor->id)
                ->latest('id')
                ->value('code');

            if ($code !== null) {
                return $url.'?ref='.urlencode($code);
            }
        }

        return $url;
    }
}
