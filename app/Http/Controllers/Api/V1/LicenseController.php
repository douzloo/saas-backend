<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LicenseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LicenseActivationRequest;
use App\Http\Resources\LicenseActivationResource;
use App\Http\Resources\LicenseResource;
use App\Models\License;
use App\Services\Interfaces\LicenseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseServiceInterface $licenseService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $licenses = $request->user()
            ->licenses()
            ->with('product')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return LicenseResource::collection($licenses);
    }

    public function show(Request $request, string $key): LicenseResource
    {
        $license = $request->user()
            ->licenses()
            ->with('product', 'activations')
            ->where('key', $key)
            ->firstOrFail();

        return new LicenseResource($license);
    }

    public function activate(LicenseActivationRequest $request): JsonResponse
    {
        $license = $request->user()
            ->licenses()
            ->where('key', $request->license_key)
            ->firstOrFail();

        try {
            $success = $this->licenseService->activate(
                $license,
                $request->domain,
                $request->identifier,
                [
                    'ip_address' => $request->ip(),
                    'hostname' => $request->hostname,
                    'platform' => $request->header('X-Platform'),
                    'php_version' => $request->header('X-PHP-Version'),
                    'app_version' => $request->header('X-App-Version'),
                    'fingerprint' => $request->header('X-Fingerprint'),
                ],
            );
        } catch (LicenseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'reason' => $e->getReason(),
            ], 422);
        }

        if (! $success) {
            return response()->json([
                'message' => 'فعال‌سازی امکان‌پذیر نیست. ممکن است تعداد فعال‌سازی‌ها به حداکثر رسیده باشد.',
            ], 422);
        }

        return response()->json([
            'message' => 'لایسنس با موفقیت فعال شد.',
            'license' => new LicenseResource($license->fresh('activations')),
        ]);
    }

    public function deactivate(LicenseActivationRequest $request): JsonResponse
    {
        $license = $request->user()
            ->licenses()
            ->where('key', $request->license_key)
            ->firstOrFail();

        $success = $this->licenseService->deactivate(
            $license,
            $request->domain,
            $request->identifier,
        );

        if (! $success) {
            return response()->json(['message' => 'دامنه یافت نشد.'], 404);
        }

        return response()->json(['message' => 'لایسنس غیرفعال شد.']);
    }

    public function verify(LicenseActivationRequest $request): JsonResponse
    {
        $license = License::where('key', $request->license_key)
            ->with('product')
            ->firstOrFail();

        $result = $this->licenseService->verify(
            $license,
            $request->domain,
            $request->identifier,
            $request->ip(),
        );

        return response()->json($result);
    }

    public function activations(Request $request, string $key): AnonymousResourceCollection
    {
        $license = $request->user()
            ->licenses()
            ->where('key', $key)
            ->firstOrFail();

        $activations = $license->activations()->get();

        return LicenseActivationResource::collection($activations);
    }

    /**
     * Desktop activate — unauthenticated, called by installed apps.
     *
     * POST /api/license/activate
     */
    public function desktopActivate(LicenseActivationRequest $request): JsonResponse
    {
        $license = License::where('key', $request->license_key)
            ->with('product')
            ->first();

        if (! $license) {
            return response()->json([
                'message' => 'لایسنس یافت نشد.',
            ], 404);
        }

        try {
            $success = $this->licenseService->activate(
                $license,
                $request->domain,
                $request->identifier,
                [
                    'ip_address' => $request->ip(),
                    'hostname' => $request->hostname,
                    'platform' => $request->header('X-Platform'),
                    'php_version' => $request->header('X-PHP-Version'),
                    'app_version' => $request->header('X-App-Version'),
                    'fingerprint' => $request->header('X-Fingerprint'),
                ],
            );
        } catch (LicenseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'reason' => $e->getReason(),
            ], 422);
        }

        if (! $success) {
            return response()->json([
                'message' => 'فعال‌سازی امکان‌پذیر نیست.',
            ], 422);
        }

        return response()->json([
            'message' => 'لایسنس با موفقیت فعال شد.',
            'license' => new LicenseResource($license->fresh('activations')),
        ]);
    }

    /**
     * Desktop deactivate — unauthenticated, called by installed apps.
     *
     * POST /api/license/deactivate
     */
    public function desktopDeactivate(LicenseActivationRequest $request): JsonResponse
    {
        $license = License::where('key', $request->license_key)
            ->first();

        if (! $license) {
            return response()->json([
                'message' => 'لایسنس یافت نشد.',
            ], 404);
        }

        $success = $this->licenseService->deactivate(
            $license,
            $request->domain,
            $request->identifier,
        );

        if (! $success) {
            return response()->json(['message' => 'دامنه یافت نشد.'], 404);
        }

        return response()->json(['message' => 'لایسنس غیرفعال شد.']);
    }

    /**
     * Desktop heartbeat — unauthenticated, called by installed apps.
     *
     * POST /api/license/heartbeat
     */
    public function heartbeat(LicenseActivationRequest $request): JsonResponse
    {
        $license = License::where('key', $request->license_key)
            ->with('product')
            ->first();

        if (! $license) {
            return response()->json([
                'success' => false,
                'message' => 'License not found.',
            ], 404);
        }

        $result = $this->licenseService->heartbeat(
            $license,
            $request->domain,
            $request->identifier,
        );

        if (! $result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Portal heartbeat — authenticated, called from customer portal.
     *
     * POST /api/portal/licenses/heartbeat
     */
    public function portalHeartbeat(LicenseActivationRequest $request): JsonResponse
    {
        $license = $request->user()
            ->licenses()
            ->where('key', $request->license_key)
            ->with('product')
            ->firstOrFail();

        $result = $this->licenseService->heartbeat(
            $license,
            $request->domain,
            $request->identifier,
        );

        if (! $result['success']) {
            return response()->json([
                'message' => 'فعال‌سازی یافت نشد.',
            ], 404);
        }

        return response()->json($result);
    }
}
