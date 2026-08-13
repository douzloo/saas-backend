<?php

namespace App\Services\Interfaces;

use App\Models\Order;
use App\Models\User;

interface OrderServiceInterface
{
    /**
     * @param  array<int, array{product_id: int|string, quantity?: int, options?: array<string, mixed>}>  $items
     * @param  array<string, mixed>  $meta
     */
    public function create(User $user, array $items, array $meta = []): Order;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function processPayment(Order $order, string $gateway, array $params = []): array;

    public function completePayment(Order $order, string $transactionId): bool;

    public function cancel(Order $order): bool;

    public function applyCoupon(Order $order, string $code): Order;
}
