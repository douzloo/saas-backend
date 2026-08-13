<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\DownloadLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Download::with('product', 'release');

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($channel = $request->query('channel')) {
            $query->forChannel($channel);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $downloads = $query->orderByDesc('version')->paginate($request->get('per_page', 20));

        return response()->json($downloads);
    }

    public function stats(): JsonResponse
    {
        $totalDownloads = (int) Download::where('is_active', true)->sum('download_count');
        $totalLogs = DownloadLog::count();

        $byProduct = Download::query()
            ->with('product:id,name')
            ->select('product_id')
            ->selectRaw('SUM(download_count) as total_downloads')
            ->groupBy('product_id')
            ->orderByDesc('total_downloads')
            ->limit(10)
            ->get()
            ->map(function (Download $row) {
                return [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product?->name,
                    'total' => (int) $row->total_downloads,
                ];
            })
            ->values();

        $byPlatform = Download::query()
            ->selectRaw('COALESCE(platform, \'unknown\') as platform')
            ->selectRaw('SUM(download_count) as total_downloads')
            ->groupBy('platform')
            ->orderByDesc('total_downloads')
            ->get()
            ->map(function (Download $row) {
                return [
                    'platform' => $row->platform,
                    'total' => (int) $row->total_downloads,
                ];
            })
            ->values();

        $topDownloads = Download::query()
            ->with('product:id,name')
            ->orderByDesc('download_count')
            ->limit(10)
            ->get()
            ->map(function (Download $download) {
                return [
                    'id' => $download->id,
                    'version' => $download->version,
                    'platform' => $download->platform,
                    'product_name' => $download->product?->name,
                    'download_count' => $download->download_count,
                ];
            })
            ->values();

        return response()->json([
            'totals' => [
                'download_count' => $totalDownloads,
                'log_entries' => $totalLogs,
            ],
            'by_product' => $byProduct,
            'by_platform' => $byPlatform,
            'top_downloads' => $topDownloads,
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $query = DownloadLog::with('download.product', 'user', 'license');

        if ($downloadId = $request->query('download_id')) {
            $query->where('download_id', $downloadId);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($licenseId = $request->query('license_id')) {
            $query->where('license_id', $licenseId);
        }

        $logs = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }
}
