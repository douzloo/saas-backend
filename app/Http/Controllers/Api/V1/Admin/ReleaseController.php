<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ReleaseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReleaseRequest;
use App\Http\Requests\UpdateReleaseRequest;
use App\Http\Resources\ProductReleaseResource;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Services\Interfaces\ProductReleaseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReleaseController extends Controller
{
    public function __construct(
        protected ProductReleaseServiceInterface $releaseService,
    ) {}

    public function index(Product $product, Request $request): AnonymousResourceCollection
    {
        $status = $request->query('status');
        $channel = $request->query('channel');

        $releases = $this->releaseService->getReleasesForProduct($product, $status, $channel);

        return ProductReleaseResource::collection($releases);
    }

    public function store(Product $product, CreateReleaseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        try {
            $release = $this->releaseService->create($product, $data);
        } catch (ReleaseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return (new ProductReleaseResource($release))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product, ProductRelease $release): ProductReleaseResource
    {
        $release->load('downloads');

        return new ProductReleaseResource($release);
    }

    public function update(
        Product $product,
        ProductRelease $release,
        UpdateReleaseRequest $request,
    ): ProductReleaseResource {
        $release = $this->releaseService->update($release, $request->validated());

        return new ProductReleaseResource($release);
    }

    public function destroy(Product $product, ProductRelease $release): JsonResponse
    {
        $this->releaseService->delete($release);

        return response()->json(null, 204);
    }

    public function publish(Product $product, ProductRelease $release): JsonResponse
    {
        try {
            $release = $this->releaseService->publish($release);
        } catch (ReleaseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(new ProductReleaseResource($release));
    }

    public function deprecate(Product $product, ProductRelease $release): JsonResponse
    {
        try {
            $release = $this->releaseService->deprecate($release);
        } catch (ReleaseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(new ProductReleaseResource($release));
    }

    public function rollback(Product $product, ProductRelease $release): JsonResponse
    {
        try {
            $release = $this->releaseService->rollback($release);
        } catch (ReleaseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(new ProductReleaseResource($release));
    }
}
