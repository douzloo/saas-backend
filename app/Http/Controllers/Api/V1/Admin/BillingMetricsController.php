<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Interfaces\InvoiceServiceInterface;
use App\Services\Interfaces\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;

class BillingMetricsController extends Controller
{
    public function __construct(
        protected InvoiceServiceInterface $invoiceService,
        protected PaymentServiceInterface $paymentService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'invoices' => $this->invoiceService->getMetrics(),
            'payments' => $this->paymentService->getMetrics(),
        ]);
    }
}
