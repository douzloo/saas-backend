<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Interfaces\OrderServiceInterface;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-side callback for Zarinpal.
 *
 * Zarinpal redirects the buyer's browser here after the payment attempt. The
 * controller is public by design (the provider must reach it), so all state
 * transitions are driven purely by the stored authority + an outbound
 * verification request to Zarinpal (never by trusting the inbound request).
 */
class ZarinpalCallbackController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected OrderServiceInterface $orderService,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $authority = (string) $request->query('Authority', '');
        $status = (string) $request->query('Status', '');

        $payment = Payment::query()
            ->with('order')
            ->where('authority', $authority)
            ->first();

        if ($payment === null || $payment->order === null) {
            Log::warning('payment.callback_unknown_authority', [
                'authority' => $authority,
                'status' => $status,
                'ip' => $request->ip(),
            ]);

            return $this->redirectToFrontend('failed', null, null);
        }

        $order = $payment->order;

        // A pending payment is not expected to hit the callback with a success
        // status — the create request never completed. Treat as invalid.
        if ($payment->authority === '') {
            Log::warning('payment.callback_missing_authority', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
            ]);

            return $this->redirectToFrontend('failed', $order, $payment);
        }

        // Status !== OK means the buyer abandoned / cancelled at the gateway.
        if (strtoupper($status) !== 'OK') {
            $this->markFailed($payment, 'پرداخت در درگاه لغو شد یا با خطا مواجه شد.');

            return $this->redirectToFrontend('failed', $order, $payment);
        }

        // Prevent double verification: an already-verified payment is a no-op.
        if ($payment->verified_at !== null || $payment->status === 'completed') {
            return $this->redirectToFrontend('success', $order, $payment);
        }

        // Reject replay of failed transactions.
        if ($payment->status === 'failed') {
            Log::warning('payment.callback_replay_attempt', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'authority' => $authority,
            ]);

            return $this->redirectToFrontend('failed', $order, $payment);
        }

        try {
            $result = $this->gatewayManager
                ->gateway($payment->gateway)
                ->verifyPayment($payment, $authority);
        } catch (PaymentGatewayException $e) {
            Log::error('payment.callback_verify_exception', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return $this->redirectToFrontend('failed', $order, $payment);
        }

        if (! $result['success']) {
            $this->markFailed($payment, $result['message']);

            return $this->redirectToFrontend('failed', $order, $payment);
        }

        $this->orderService->completePayment($order, $payment->transaction_id);

        Log::info('payment.callback_completed', [
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'ref_id' => $result['ref_id'] ?? null,
            'authority' => $authority,
        ]);

        return $this->redirectToFrontend('success', $order, $payment);
    }

    protected function markFailed(Payment $payment, string $reason): void
    {
        $payment->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'failed_at' => now(),
        ]);
    }

    protected function redirectToFrontend(string $status, ?Order $order, ?Payment $payment): RedirectResponse
    {
        $base = rtrim((string) config('payments.redirect_base_url', config('app.url')), '/');

        $query = [
            'status' => $status,
        ];

        if ($order !== null) {
            $query['order'] = $order->id;
            $query['order_number'] = $order->order_number;
        }

        if ($payment !== null) {
            $query['payment'] = $payment->id;
        }

        return redirect()->away(
            $base.'/checkout/result?'.http_build_query($query)
        );
    }
}
