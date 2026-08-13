<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Interfaces\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentServiceInterface $paymentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Payment::with('invoice')->orderByDesc('created_at');

        if ($user->isStaff()) {
            if ($request->has('invoice_id')) {
                $query->where('invoice_id', $request->invoice_id);
            }
        } else {
            $query->whereHas('invoice', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return PaymentResource::collection($query->paginate($request->get('per_page', 15)));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['invoice_id'])) {
            return response()->json(['message' => 'invoice_id is required.'], 422);
        }

        $invoice = Invoice::find((int) $data['invoice_id']);
        abort_if($invoice === null, 404, 'Invoice not found.');

        $payment = $this->paymentService->createForInvoice($invoice, $data);

        return response()->json([
            'data' => new PaymentResource($payment->load('invoice')),
        ], 201);
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        $user = $request->user();

        if (! $user->isStaff()
            && ($payment->invoice === null || $payment->invoice->user_id !== $user->id)) {
            abort(403);
        }

        return new PaymentResource($payment->load('invoice'));
    }

    public function refund(Request $request, Payment $payment): JsonResponse
    {
        if (! $request->user()->isStaff()) {
            abort(403);
        }

        $request->validate([
            'amount' => 'nullable|numeric|gt:0',
            'reason' => 'nullable|string',
        ]);

        try {
            $payment = $this->paymentService->refund(
                $payment,
                $request->input('amount')
                    ? (float) $request->input('amount')
                    : null,
                $request->input('reason')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => new PaymentResource($payment->load('invoice')),
        ]);
    }
}
