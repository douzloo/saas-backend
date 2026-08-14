<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zarinpal payment gateway (REST API v4).
 *
 * Supports sandbox and production mode, controlled by the
 * ZARINPAL_SANDBOX environment variable. All amounts are stored in IRR and
 * converted to Toman (IRR / 10) on the wire, as required by the Zarinpal API.
 */
class ZarinpalGateway implements PaymentGatewayInterface
{
    /** Success codes returned by the Zarinpal verify endpoint. */
    private const VERIFY_SUCCESS_CODES = [100, 101];

    /**
     * @return array{authority: string, redirect_url: string, status: string, message: string}
     */
    public function createPayment(Order $order, Payment $payment): array
    {
        $payload = [
            'merchant_id' => $this->merchantId(),
            'amount' => $this->toToman((float) $payment->amount),
            'callback_url' => $this->callbackUrl(),
            'description' => 'سفارش '.$order->order_number,
            'metadata' => [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'customer_email' => $order->user?->email,
            ],
        ];

        $body = $this->request('payment/request.json', $payload, $payment);

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $authority = (string) ($data['authority'] ?? '');

        if ($authority === '' || (int) ($body['code'] ?? 0) !== 100) {
            throw PaymentGatewayException::withContext(
                'درگاه پرداخت درخواست را رد کرد: '.($body['message'] ?? 'خطای نامشخص'),
                ['payment_id' => $payment->id, 'response' => $body],
            );
        }

        $payment->forceFill([
            'authority' => $authority,
            'gateway_response' => $body,
        ])->save();

        Log::info('zarinpal.payment_created', [
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'authority' => $authority,
            'amount_irr' => $payment->amount,
            'sandbox' => $this->isSandbox(),
        ]);

        return [
            'authority' => $authority,
            'redirect_url' => $this->startPayUrl($authority),
            'status' => 'pending',
            'message' => trim((string) ($body['message'] ?? '')),
        ];
    }

    /**
     * @return array{success: bool, ref_id: string|null, code: int|null, message: string}
     */
    public function verifyPayment(Payment $payment, string $authority): array
    {
        $body = $this->request('payment/verify.json', [
            'merchant_id' => $this->merchantId(),
            'amount' => $this->toToman((float) $payment->amount),
            'authority' => $authority,
        ], $payment);

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $code = isset($data['code']) ? (int) $data['code'] : null;

        if (in_array($code, self::VERIFY_SUCCESS_CODES, true)) {
            $refId = (string) ($data['ref_id'] ?? '');

            $payment->forceFill([
                'ref_id' => $refId !== '' ? $refId : null,
                'verified_at' => now(),
                'gateway_response' => $body,
                'failure_reason' => null,
            ])->save();

            Log::info('zarinpal.payment_verified', [
                'payment_id' => $payment->id,
                'authority' => $authority,
                'ref_id' => $refId,
                'code' => $code,
            ]);

            return [
                'success' => true,
                'ref_id' => $refId !== '' ? $refId : null,
                'code' => $code,
                'message' => trim((string) ($data['message'] ?? '')),
            ];
        }

        $payment->forceFill([
            'gateway_response' => $body,
            'failed_at' => now(),
            'failure_reason' => trim((string) ($data['message'] ?? 'Verification failed')),
        ])->save();

        Log::warning('zarinpal.payment_verify_rejected', [
            'payment_id' => $payment->id,
            'authority' => $authority,
            'code' => $code,
            'response' => $body,
        ]);

        return [
            'success' => false,
            'ref_id' => null,
            'code' => $code,
            'message' => trim((string) ($data['message'] ?? 'Verification failed')),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function refundPayment(Payment $payment): array
    {
        if (! $payment->authority) {
            throw PaymentGatewayException::withContext(
                'امکان استرداد بدون آتوریتی وجود ندارد.',
                ['payment_id' => $payment->id],
            );
        }

        $body = $this->request('payment/refund.json', [
            'merchant_id' => $this->merchantId(),
            'authority' => $payment->authority,
            'amount' => $this->toToman((float) $payment->getRefundableAmount()),
        ], $payment);

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $code = isset($data['code']) ? (int) $data['code'] : (int) ($body['code'] ?? 0);

        $payment->forceFill([
            'gateway_response' => $body,
        ])->save();

        if (! in_array($code, self::VERIFY_SUCCESS_CODES, true)) {
            Log::warning('zarinpal.payment_refund_rejected', [
                'payment_id' => $payment->id,
                'authority' => $payment->authority,
                'code' => $code,
                'response' => $body,
            ]);

            return [
                'success' => false,
                'message' => trim((string) ($data['message'] ?? 'Refund failed')),
            ];
        }

        Log::info('zarinpal.payment_refunded', [
            'payment_id' => $payment->id,
            'authority' => $payment->authority,
            'code' => $code,
        ]);

        return [
            'success' => true,
            'message' => trim((string) ($data['message'] ?? '')),
        ];
    }

    /**
     * Send a JSON request to the Zarinpal API and return the decoded body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $endpoint, array $payload, Payment $payment): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->post($this->apiUrl($endpoint), $payload);
        } catch (ConnectionException $e) {
            throw PaymentGatewayException::withContext(
                'درگاه پرداخت قابل دسترسی نیست.',
                ['payment_id' => $payment->id, 'endpoint' => $endpoint, 'error' => $e->getMessage()],
            );
        }

        if ($response->failed()) {
            throw PaymentGatewayException::withContext(
                'درگاه پرداخت با خطا پاسخ داد (HTTP '.$response->status().').',
                ['payment_id' => $payment->id, 'endpoint' => $endpoint, 'status' => $response->status()],
            );
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    protected function merchantId(): string
    {
        $id = (string) config('payments.gateways.zarinpal.merchant_id');

        if ($id === '') {
            throw PaymentGatewayException::withContext(
                'ZARINPAL_MERCHANT_ID در تنظیمات تعریف نشده است.'
            );
        }

        return $id;
    }

    protected function callbackUrl(): string
    {
        return (string) (config('payments.gateways.zarinpal.callback_url')
            ?? rtrim(config('app.url'), '/').'/api/payment/callback/zarinpal');
    }

    protected function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.zarinpal.com'
            : 'https://payment.zarinpal.com';
    }

    protected function apiUrl(string $endpoint): string
    {
        return $this->baseUrl().'/pg/v4/'.$endpoint;
    }

    protected function startPayUrl(string $authority): string
    {
        return $this->baseUrl().'/pg/StartPay/'.$authority;
    }

    protected function isSandbox(): bool
    {
        return (bool) config('payments.gateways.zarinpal.sandbox', true);
    }

    /**
     * The Zarinpal REST API expects the amount in Toman (1/10 of a Rial).
     */
    protected function toToman(float $rials): int
    {
        return (int) round($rials / 10);
    }
}
