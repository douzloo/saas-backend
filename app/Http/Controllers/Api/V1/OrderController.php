<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\LicenseResource;
use App\Http\Resources\OrderResource;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Interfaces\OrderServiceInterface;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        protected OrderServiceInterface $orderService,
        protected PaymentGatewayManager $gatewayManager,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with('items.product')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): OrderResource
    {
        $order = $this->orderService->create(
            $request->user(),
            $request->items,
            [
                'coupon_code' => $request->coupon_code,
                'notes' => $request->notes,
                'billing_address' => $request->billing_address,
            ]
        );

        return new OrderResource($order->load('items.product'));
    }

    public function show(Request $request, Order $order): OrderResource
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isStaff()) {
            abort(403);
        }

        return new OrderResource($order->load(['items.product', 'payments', 'invoice']));
    }

    public function applyCoupon(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isStaff()) {
            abort(403);
        }

        $request->validate([
            'coupon_code' => 'required|string',
        ]);

        try {
            $order = $this->orderService->applyCoupon($order, $request->coupon_code);

            return response()->json(new OrderResource($order));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'gateway' => 'required|in:zarinpal,mellat,saman,pay_ir,idpay',
        ]);

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'سفارش قابل پرداخت نیست.'], 422);
        }

        try {
            $result = $this->orderService->processPayment($order, $request->gateway);
        } catch (NotImplementedException $e) {
            return response()->json(['message' => 'درگاه پرداخت موردنظر هنوز فعال نشده است.'], 422);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Confirm a successful gateway payment, complete the order and issue the
     * purchased licenses (which grant download access). Idempotent — the
     * frontend may call this on return from the gateway or on page reload.
     *
     * Verifies the transaction with the payment provider through the gateway
     * manager before completing the order.
     */
    public function verifyPayment(Request $request, Order $order, Payment $payment): JsonResponse
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isStaff()) {
            abort(403);
        }

        if ($payment->order_id !== $order->id) {
            abort(404);
        }

        if ($payment->status === 'failed') {
            return response()->json(['message' => 'پرداخت ناموفق است.'], 422);
        }

        // Already verified / completed — return the current state without
        // contacting the provider again (prevents double verification).
        if ($payment->status === 'completed') {
            return $this->verificationResponse($order, $payment);
        }

        if ($payment->authority === null || $payment->authority === '') {
            return response()->json(['message' => 'تراکنش فاقد آتوریتی درگاه است.'], 422);
        }

        try {
            $result = $this->gatewayManager
                ->gateway($payment->gateway)
                ->verifyPayment($payment, $payment->authority);
        } catch (PaymentGatewayException $e) {
            Log::error('payment.verify_exception', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return response()->json(['message' => 'تأیید پرداخت در درگاه ناموفق بود.'], 422);
        }

        if (! $result['success']) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $result['message'],
                'failed_at' => now(),
            ]);

            return response()->json(['message' => 'پرداخت توسط درگاه تأیید نشد.'], 422);
        }

        $completed = $this->orderService->completePayment($order, $payment->transaction_id);

        if (! $completed) {
            return response()->json(['message' => 'تراکنش یافت نشد.'], 422);
        }

        return $this->verificationResponse($order, $payment);
    }

    protected function verificationResponse(Order $order, Payment $payment): JsonResponse
    {
        $order->load(['items.product', 'payments', 'invoice']);

        $licenses = License::query()
            ->where('user_id', $order->user_id)
            ->whereIn('product_id', $order->items->pluck('product_id')->unique())
            ->latest()
            ->get();

        return response()->json([
            'data' => new OrderResource($order),
            'licenses' => LicenseResource::collection($licenses),
        ]);
    }

    public function invoice(Request $request, Order $order): InvoiceResource
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isStaff()) {
            abort(403);
        }

        $invoice = $order->invoice;

        abort_if(! $invoice, 404, 'فاکتور یافت نشد.');

        return new InvoiceResource($invoice);
    }
}
