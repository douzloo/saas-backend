<?php

namespace App\Services\Payments\Contracts;

use App\Models\Order;
use App\Models\Payment;

/**
 * Contract for payment gateway integrations.
 *
 * Gateways implement the raw communication with their provider (request /
 * verify / refund) and return normalized results. Persisting gateway-specific
 * fields (authority, ref_id, gateway_response, …) is the responsibility of
 * the gateway; order/license lifecycle transitions stay in OrderService.
 */
interface PaymentGatewayInterface
{
    /**
     * Start a payment for an order against the gateway provider.
     *
     * Persists the gateway authority + full provider response on the payment
     * and returns a normalized result.
     *
     * @return array{authority: string, redirect_url: string, status: string, message: string}
     */
    public function createPayment(Order $order, Payment $payment): array;

    /**
     * Ask the provider to confirm a previously started payment.
     *
     * @return array{success: bool, ref_id: string|null, code: int|null, message: string}
     */
    public function verifyPayment(Payment $payment, string $authority): array;

    /**
     * Request a (partial or full) refund from the provider.
     *
     * @return array{success: bool, message: string}
     */
    public function refundPayment(Payment $payment): array;
}
