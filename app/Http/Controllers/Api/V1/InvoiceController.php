<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayInvoiceRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Services\Interfaces\InvoiceServiceInterface;
use App\Services\Interfaces\PaymentServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceServiceInterface $invoiceService,
        protected PaymentServiceInterface $paymentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = $user->invoices()
            ->with(['invoiceItems.product', 'payments'])
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($user->isStaff()) {
            $query = Invoice::query()->with(['invoiceItems.product', 'payments', 'user'])
                ->orderByDesc('created_at');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
        }

        return InvoiceResource::collection($query->paginate($request->get('per_page', 15)));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create(
            $request->user(),
            $request->validated(),
            $request->input('items', [])
        );

        return response()->json([
            'data' => new InvoiceResource($invoice->load('invoiceItems', 'payments', 'user')),
        ], 201);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorizeView($request, $invoice);

        return new InvoiceResource($invoice->load([
            'invoiceItems.product',
            'payments',
            'user',
            'order',
        ]));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorizeManage($request, $invoice);

        $invoice = $this->invoiceService->update(
            $invoice,
            $request->validated(),
            $request->input('items', [])
        );

        return new InvoiceResource($invoice->load('invoiceItems', 'payments', 'user'));
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeManage($request, $invoice);

        $this->invoiceService->delete($invoice);

        return response()->json(['message' => 'Invoice deleted.']);
    }

    public function pay(PayInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeView($request, $invoice);

        if ($invoice->isPaid()) {
            return response()->json(['message' => 'Invoice already paid.'], 422);
        }

        if ($invoice->isCancelled()) {
            return response()->json(['message' => 'Cancelled invoices cannot be paid.'], 422);
        }

        $amount = $request->input('amount')
            ?? (float) $invoice->balance_due;

        $payment = $this->paymentService->createForInvoice($invoice, [
            'amount' => $amount,
            'gateway' => $request->input('gateway', 'manual'),
            'reference_id' => $request->input('reference_id'),
            'currency' => $request->input('currency', $invoice->currency),
            'status' => 'completed',
        ]);

        return response()->json([
            'message' => 'Payment recorded.',
            'data' => new PaymentResource($payment->load('invoice')),
        ], 201);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorizeView($request, $invoice);

        $invoice->load(['invoiceItems', 'user', 'payments']);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    protected function authorizeView(Request $request, Invoice $invoice): void
    {
        if ($invoice->user_id !== $request->user()->id && ! $request->user()->isStaff()) {
            abort(403);
        }
    }

    protected function authorizeManage(Request $request, Invoice $invoice): void
    {
        if (! $request->user()->isStaff()) {
            abort(403);
        }
    }
}
