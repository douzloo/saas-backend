<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\FaqResource;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductReleaseResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::active()->orderable();

        if ($request->has('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $products = $query->with('categories')->paginate($request->get('per_page', 12));

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product->load([
            'categories',
            'faqs' => fn ($q) => $q->published()->orderBy('sort_order'),
            'publishedReleases' => fn ($q) => $q->orderByDesc('created_at'),
        ]);

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $product = Product::create($request->safe()->except(['categories']));

        if ($request->filled('categories')) {
            $product->categories()->sync($request->categories);
        }

        return new ProductResource($product->load('categories'));
    }

    public function update(StoreProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->safe()->except(['categories']));

        if ($request->has('categories')) {
            $product->categories()->sync($request->categories ?? []);
        }

        return new ProductResource($product->load('categories'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'محصول حذف شد.'], 200);
    }

    public function categories(): AnonymousResourceCollection
    {
        $categories = ProductCategory::with('children', 'products')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return ProductCategoryResource::collection($categories);
    }

    public function faqs(Product $product): AnonymousResourceCollection
    {
        $faqs = $product->faqs()
            ->published()
            ->orderBy('sort_order')
            ->get();

        return FaqResource::collection($faqs);
    }

    public function releases(Product $product): AnonymousResourceCollection
    {
        $releases = $product->releases()
            ->published()
            ->orderByDesc('created_at')
            ->get();

        return ProductReleaseResource::collection($releases);
    }

    public function latestRelease(Product $product): ProductReleaseResource
    {
        $release = $product->releases()
            ->published()
            ->orderByDesc('version')
            ->first();

        abort_if(! $release, 404, 'Release not found.');

        return new ProductReleaseResource($release);
    }
}
