<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenewLicenseRequest;
use App\Http\Requests\SuspendLicenseRequest;
use App\Http\Requests\TransferLicenseRequest;
use App\Http\Requests\UpgradeLicenseRequest;
use App\Http\Resources\LicenseActivationResource;
use App\Http\Resources\LicenseResource;
use App\Models\License;
use App\Services\Interfaces\LicenseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LicenseAdminController extends Controller
{
    public function __construct(
        protected LicenseServiceInterface $licenseService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = License::with('user', 'product');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('key', 'like', "%{$request->search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$request->search}%"));
            });
        }

        $licenses = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return LicenseResource::collection($licenses);
    }

    public function show(License $license): LicenseResource
    {
        return new LicenseResource($license->load('user', 'product', 'activations'));
    }

    public function activations(License $license): AnonymousResourceCollection
    {
        return LicenseActivationResource::collection($license->activations()->orderByDesc('created_at')->get());
    }

    public function suspend(SuspendLicenseRequest $request, License $license): LicenseResource
    {
        $this->licenseService->suspend($license, $request->reason);

        return new LicenseResource($license->fresh('user', 'product', 'activations'));
    }

    public function unsuspend(License $license): LicenseResource
    {
        $this->licenseService->unsuspend($license);

        return new LicenseResource($license->fresh('user', 'product', 'activations'));
    }

    public function revoke(License $license): LicenseResource
    {
        $this->licenseService->revoke($license);

        return new LicenseResource($license->fresh('user', 'product', 'activations'));
    }

    public function renew(RenewLicenseRequest $request, License $license): LicenseResource
    {
        $license = $this->licenseService->renew($license, $request->days);

        return new LicenseResource($license->load('user', 'product'));
    }

    public function upgrade(UpgradeLicenseRequest $request, License $license): LicenseResource|JsonResponse
    {
        try {
            $license = $this->licenseService->upgrade($license, $request->only(['type', 'max_activations']));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new LicenseResource($license->load('user', 'product'));
    }

    public function transfer(TransferLicenseRequest $request, License $license): LicenseResource|JsonResponse
    {
        try {
            $license = $this->licenseService->transfer($license, $request->user_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new LicenseResource($license->load('user', 'product'));
    }

    public function staleActivations(): JsonResponse
    {
        return response()->json([
            'data' => $this->licenseService->staleActivations()->map(fn ($a) => [
                'id' => $a->id,
                'license_key' => $a->license?->key,
                'product' => $a->license?->product?->name,
                'domain' => $a->domain,
                'identifier' => $a->identifier,
                'last_heartbeat_at' => $a->last_heartbeat_at?->toIso8601String(),
            ]),
        ]);
    }
}
