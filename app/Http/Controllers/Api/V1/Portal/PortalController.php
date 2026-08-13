<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\DownloadResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\LicenseResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TicketResource;
use App\Models\Download;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PortalController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $orderQuery = $user->orders();
        $licenseQuery = $user->licenses();
        $ticketQuery = $user->tickets();

        if ($request->has('product_id')) {
            $orderQuery->whereHas('items', fn ($q) => $q->where('product_id', $request->product_id));
            $licenseQuery->where('product_id', $request->product_id);
            $ticketQuery->where('product_id', $request->product_id);
        }

        $paidAmount = (clone $orderQuery)->where('status', 'completed')->sum('total');
        $pendingAmount = (clone $orderQuery)->where('status', 'pending')->sum('total');

        return response()->json([
            'stats' => [
                'orders' => [
                    'total' => (clone $orderQuery)->count(),
                    'completed' => (clone $orderQuery)->where('status', 'completed')->count(),
                    'pending' => (clone $orderQuery)->where('status', 'pending')->count(),
                    'total_spent' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                ],
                'licenses' => [
                    'total' => (clone $licenseQuery)->count(),
                    'active' => (clone $licenseQuery)->where('status', 'active')->count(),
                    'expiring_soon' => (clone $licenseQuery)
                        ->where('status', 'active')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now())
                        ->where('expires_at', '<', now()->addDays(30))
                        ->count(),
                ],
                'tickets' => [
                    'total' => (clone $ticketQuery)->count(),
                    'open' => (clone $ticketQuery)->whereIn('status', ['open', 'in_progress', 'waiting_reply'])->count(),
                ],
                'downloads' => $user->downloadLogs()->count(),
            ],
            'recent_orders' => OrderResource::collection(
                $user->orders()->with('items.product')->orderByDesc('created_at')->limit(5)->get()
            ),
            'recent_licenses' => LicenseResource::collection(
                $user->licenses()->with('product')->orderByDesc('created_at')->limit(5)->get()
            ),
            'recent_tickets' => TicketResource::collection(
                $user->tickets()->with('product')->orderByDesc('created_at')->limit(5)->get()
            ),
        ]);
    }

    public function invoices(Request $request): AnonymousResourceCollection
    {
        $invoices = $request->user()
            ->invoices()
            ->with(['invoiceItems', 'payments'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return InvoiceResource::collection($invoices);
    }

    public function invoice(Request $request, Invoice $invoice): InvoiceResource
    {
        if ($invoice->user_id !== $request->user()->id && ! $request->user()->isStaff()) {
            abort(403);
        }

        return new InvoiceResource($invoice->load([
            'invoiceItems.product',
            'payments',
            'order',
        ]));
    }

    public function downloads(Request $request): AnonymousResourceCollection
    {
        $productIds = $request->user()->licenses()->where('status', 'active')->pluck('product_id');

        $downloads = Download::query()
            ->whereIn('product_id', $productIds)
            ->active()
            ->with(['product', 'release'])
            ->orderByDesc('version')
            ->paginate($request->get('per_page', 15));

        return DownloadResource::collection($downloads);
    }
}
