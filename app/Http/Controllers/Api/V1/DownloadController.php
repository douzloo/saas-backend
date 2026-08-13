<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\DownloadLog;
use App\Models\License;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Download::active()->with('product');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($channel = $request->query('channel')) {
            $query->forChannel($channel);
        }

        $downloads = $query->orderByDesc('version')->paginate($request->get('per_page', 20));

        return response()->json($downloads);
    }

    public function latest(Request $request): JsonResponse
    {
        $query = Download::active()
            ->with(['product', 'release']);

        if ($channel = $request->query('channel')) {
            $query->forChannel($channel);
        } else {
            $query->stable();
        }

        $downloads = $query->orderByDesc('version')
            ->limit(10)
            ->get();

        return response()->json($downloads);
    }

    public function byProduct(Product $product, Request $request): JsonResponse
    {
        $platform = $request->query('platform');
        $channel = $request->query('channel');

        $query = $product->downloads()
            ->active()
            ->with('release');

        if ($channel) {
            $query->forChannel($channel);
        } elseif ($product->releases()->exists()) {
            $query->whereHas('release', function ($q) {
                $q->where('status', 'published');
            });
        }

        if ($platform) {
            $query->where('platform', $platform);
        } else {
            $query->where(function ($q) {
                $q->where('platform', 'universal')
                    ->orWhereNull('platform');
            });
        }

        $downloads = $query->orderByDesc('version')->get();

        return response()->json($downloads);
    }

    public function download(Download $download, Request $request): JsonResponse
    {
        if (! $download->is_active) {
            return response()->json(['message' => 'فایل در دسترس نیست.'], 404);
        }

        if (! $this->canDownload($download, $request)) {
            return response()->json(['message' => 'برای دانلود این فایل نیاز به لایسنس معتبر دارید.'], 403);
        }

        $license = $request->attributes->get('resolved_license');

        DownloadLog::create([
            'download_id' => $download->id,
            'user_id' => auth()->id(),
            'license_id' => $license?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $download->incrementDownloadCount();

        return response()->json([
            'download_url' => $download->download_url,
            'filename' => $download->original_filename,
            'version' => $download->version,
            'file_size' => $download->formatted_size,
            'file_hash' => $download->file_hash,
            'changelog' => $download->changelog,
            'release' => $download->release,
        ]);
    }

    /**
     * Resolve download permission.
     *
     * A download is accessible without a license when the product does not
     * require one, or when the requester is an authenticated admin/staff.
     * Otherwise a valid license key for the product is required.
     */
    protected function canDownload(Download $download, Request $request): bool
    {
        // The public download route has no `auth:sanctum` middleware, so resolve
        // the Bearer-token user via the Sanctum guard explicitly.
        $user = $request->user('sanctum');

        if ($user !== null && $user->isStaff()) {
            return true;
        }

        if (! $download->product->requires_activation) {
            return true;
        }

        if ($user !== null && $user->licenses()->where('product_id', $download->product_id)->valid()->exists()) {
            return true;
        }

        $license = License::where('key', $request->input('license_key'))
            ->where('product_id', $download->product_id)
            ->valid()
            ->first();

        if ($license !== null) {
            $request->attributes->set('resolved_license', $license);

            return true;
        }

        return false;
    }
}
