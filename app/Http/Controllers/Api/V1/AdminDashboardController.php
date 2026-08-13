<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\Lead;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

class AdminDashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            static function ($request, $next) {
                if (! $request->user()->isAdmin()) {
                    abort(403, 'Unauthorized');
                }

                return $next($request);
            },
        ];
    }

    public function overview(): JsonResponse
    {
        return response()->json([
            'users' => [
                'total' => User::count(),
                'customers' => User::where('role', 'customer')->count(),
                'staff' => User::where('role', 'staff')->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
            ],
            'orders' => [
                'total' => Order::count(),
                'completed' => Order::where('status', 'completed')->count(),
                'pending' => Order::where('status', 'pending')->count(),
                'revenue_today' => Order::where('status', 'completed')
                    ->whereDate('paid_at', today())
                    ->sum('total'),
                'revenue_month' => Order::where('status', 'completed')
                    ->whereMonth('paid_at', now()->month)
                    ->sum('total'),
            ],
            'licenses' => [
                'total' => License::count(),
                'active' => License::where('status', 'active')->count(),
                'expired' => License::expired()->count(),
                'activations' => LicenseActivation::count(),
                'active_activations' => LicenseActivation::where('is_active', true)->count(),
            ],
            'tickets' => [
                'total' => Ticket::count(),
                'open' => Ticket::open()->count(),
            ],
            'leads' => [
                'total' => Lead::count(),
                'new' => Lead::where('status', 'new')->count(),
                'won_value' => Lead::where('status', 'won')->sum('estimated_value'),
            ],
            'downloads' => [
                'total' => Download::sum('download_count'),
            ],
        ]);
    }

    public function recentOrders(): JsonResponse
    {
        $orders = Order::with('user', 'items.product')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json($orders);
    }

    public function recentLeads(): JsonResponse
    {
        $leads = Lead::with('assignee')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json($leads);
    }

    public function recentTickets(): JsonResponse
    {
        $tickets = Ticket::with('user', 'assignee')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json($tickets);
    }
}
