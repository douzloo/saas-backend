<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Interfaces\OrderServiceInterface;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        protected LicenseService $licenseService,
        protected PaymentGatewayManager $gatewayManager,
    ) {}

    /**
     * @param  array<int, array{product_id: int|string, quantity?: int, options?: array<string, mixed>}>  $items
     * @param  array<string, mixed>  $meta
     */
    public function create(User $user, array $items, array $meta = []): Order
    {
        return DB::transaction(function () use ($user, $items, $meta) {
            $subtotal = 0;
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'subtotal' => 0,
                'discount' => $meta['discount'] ?? 0,
                'tax' => 0,
                'total' => 0,
                'currency' => 'IRR',
                'coupon_code' => $meta['coupon_code'] ?? null,
                'billing_address' => $meta['billing_address'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $quantity = $item['quantity'] ?? 1;
                $unitPrice = $product->price;
                $totalPrice = $unitPrice * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'options' => $item['options'] ?? null,
                ]);

                $subtotal += $totalPrice;
            }

            $discount = $order->discount;
            $tax = ($subtotal - $discount) * 0.10;
            $total = $subtotal - $discount + $tax;

            $order->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);

            return $order;
        });
    }

    public function processPayment(Order $order, string $gateway, array $params = []): array
    {
        $payment = $order->payments()->create([
            'transaction_id' => 'TXN-'.strtoupper(uniqid()),
            'gateway' => $gateway,
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        $order->update(['status' => 'processing']);

        try {
            $result = $this->gatewayManager
                ->gateway($gateway)
                ->createPayment($order, $payment);
        } catch (PaymentGatewayException $e) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            Log::error('payment.gateway_create_failed', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            throw $e;
        }

        return [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'authority' => $result['authority'],
            'amount' => $order->total,
            'gateway' => $gateway,
            'redirect_url' => $result['redirect_url'],
        ];
    }

    public function completePayment(Order $order, string $transactionId): bool
    {
        return DB::transaction(function () use ($order, $transactionId) {
            $payment = $order->payments()
                ->where('transaction_id', $transactionId)
                ->first();

            if (! $payment) {
                return false;
            }

            // Idempotent: an already-completed payment is a no-op. This makes
            // repeated gateway callbacks / double verifies safe.
            if ($payment->status === 'completed') {
                return true;
            }

            // A payment that failed at the gateway can never be completed by a
            // later callback / verify — prevents replay of failed transactions.
            if ($payment->status === 'failed') {
                return false;
            }

            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            $order->update([
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $this->licenseService->create([
                        'product_id' => $item->product_id,
                        'user_id' => $order->user_id,
                        'type' => 'standard',
                        'status' => 'active',
                        'price' => $item->unit_price,
                        'max_activations' => $item->product->max_domains,
                        'expires_at' => $item->product->trial_days > 0
                            ? null
                            : now()->addYear(),
                    ]);
                }
            }

            return true;
        });
    }

    public function cancel(Order $order): bool
    {
        if ($order->status === 'completed') {
            return false;
        }

        $order->update(['status' => 'cancelled']);
        $order->payments()->where('status', 'pending')->update(['status' => 'failed']);

        return true;
    }

    public function applyCoupon(Order $order, string $code): Order
    {
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon || ! $coupon->isValid()) {
            throw new \InvalidArgumentException('Invalid coupon code');
        }

        $discount = $coupon->calculateDiscount((float) $order->subtotal);
        $tax = ($order->subtotal - $discount) * 0.10;

        $order->update([
            'coupon_code' => $code,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $order->subtotal - $discount + $tax,
        ]);

        $coupon->apply();

        return $order;
    }
}
