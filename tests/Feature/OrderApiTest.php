<?php

use App\Models\Download;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Configure a faked Zarinpal gateway (sandbox) so order /pay calls and
 * /verify calls never touch the real provider.
 *
 * @param  array<string, array<string, mixed>>  $overrides
 */
function fakeZarinpal(array $overrides = []): void
{
    config()->set('payments.gateways.zarinpal.merchant_id', 'test-merchant');
    config()->set('payments.gateways.zarinpal.sandbox', true);

    Http::fake([
        'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(
            array_merge([
                'data' => ['authority' => 'A000000000000000000000000000000001'],
                'errors' => [],
                'code' => 100,
                'message' => 'Success',
            ], $overrides['request'] ?? [])
        ),
        'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response(
            array_merge([
                'data' => ['code' => 100, 'ref_id' => '100000000', 'message' => 'Verified'],
                'errors' => [],
                'code' => 100,
                'message' => 'Success',
            ], $overrides['verify'] ?? [])
        ),
    ]);
}

it('requires authentication to list orders', function () {
    $this->getJson('/api/orders')->assertUnauthorized();
});

it('lists authenticated user orders', function () {
    $user = User::factory()->create();
    Order::factory()->count(2)->for($user)->create();
    Order::factory()->for(User::factory())->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/orders');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('creates an order with items', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100000]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    $this->assertDatabaseHas('order_items', [
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '100000.00',
        'total_price' => '200000.00',
    ]);
});

it('validates order items are required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', ['items' => []]);

    $response->assertStatus(422)->assertJsonValidationErrors('items');
});

it('rejects order with invalid product', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'items' => [
                ['product_id' => 99999, 'quantity' => 1],
            ],
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
});

it('shows order to owner', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/orders/{$order->id}");

    $response->assertOk()->assertJsonPath('data.id', $order->id);
});

it('forbids showing order to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::factory()->for($other)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/orders/{$order->id}");

    $response->assertForbidden();
});

it('allows staff to view any order', function () {
    $staff = User::factory()->staff()->create();
    $other = User::factory()->create();
    $order = Order::factory()->for($other)->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->getJson("/api/orders/{$order->id}");

    $response->assertOk();
});

it('processes payment for a pending order', function () {
    fakeZarinpal();

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => 'pending', 'total' => 50000]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/pay", ['gateway' => 'zarinpal']);

    $response->assertOk()
        ->assertJsonStructure(['payment_id', 'transaction_id', 'authority', 'amount', 'gateway', 'redirect_url'])
        ->assertJsonPath('amount', '50000.00')
        ->assertJsonPath('authority', 'A000000000000000000000000000000001');

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'gateway' => 'zarinpal',
        'authority' => 'A000000000000000000000000000000001',
    ]);
});

it('rejects payment for non-pending order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->completed()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/pay", ['gateway' => 'zarinpal']);

    $response->assertStatus(422);
});

it('verifies a payment, completes the order and issues licenses', function () {
    fakeZarinpal();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'price' => 100000,
        'requires_activation' => true,
        'trial_days' => 0,
        'max_domains' => 3,
    ]);

    $order = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->json('data');

    $pay = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/pay", ['gateway' => 'zarinpal'])
        ->assertOk()->json();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/payments/{$pay['payment_id']}/verify")
        ->assertOk();

    $response->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.id', $order['id']);

    $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'completed']);
    $this->assertDatabaseHas('payments', ['id' => $pay['payment_id'], 'status' => 'completed']);
    $this->assertDatabaseCount('licenses', 2);
    $this->assertDatabaseHas('licenses', [
        'user_id' => $user->id,
        'product_id' => $product->id,
        'status' => 'active',
        'max_activations' => 3,
    ]);

    expect($response->json('licenses'))->toHaveCount(2);
});

it('is idempotent when verifying an already completed payment', function () {
    fakeZarinpal();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'price' => 100000,
        'requires_activation' => true,
        'trial_days' => 0,
    ]);

    $order = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id]]])
        ->json('data');

    $pay = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/pay", ['gateway' => 'zarinpal'])
        ->json();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/payments/{$pay['payment_id']}/verify")
        ->assertOk();
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/payments/{$pay['payment_id']}/verify")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseCount('licenses', 1);
});

it('lets the purchased user download a license-gated file', function () {
    fakeZarinpal();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'price' => 100000,
        'requires_activation' => true,
        'trial_days' => 0,
    ]);
    $download = Download::factory()->create([
        'product_id' => $product->id,
    ]);

    $order = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id]]])
        ->json('data');

    $pay = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/pay", ['gateway' => 'zarinpal'])
        ->json();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/payments/{$pay['payment_id']}/verify")
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/downloads/{$download->id}")
        ->assertOk()
        ->assertJsonPath('download_url', $download->download_url);
});

it('does not allow verifying another users order payment', function () {
    fakeZarinpal();

    $user = User::factory()->create();
    $other = User::factory()->create();
    $product = Product::factory()->create(['price' => 100000, 'requires_activation' => true]);

    $order = $this->actingAs($other, 'sanctum')
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id]]])
        ->json('data');

    $pay = $this->actingAs($other, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/pay", ['gateway' => 'zarinpal'])
        ->json();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/payments/{$pay['payment_id']}/verify")
        ->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'processing']);
    $this->assertDatabaseCount('licenses', 0);
});

it('rejects verifying a payment that does not belong to the order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100000]);

    $order = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id]]])
        ->json('data');

    $otherOrder = Order::factory()->for($user)->create(['status' => 'pending']);
    $foreignPay = $otherOrder->payments()->create([
        'transaction_id' => 'TXN-FOREIGN',
        'gateway' => 'zarinpal',
        'status' => 'pending',
        'amount' => 1000,
        'currency' => 'IRR',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/payments/{$foreignPay->id}/verify")
        ->assertNotFound();
});

it('rejects verifying a failed payment', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => 'processing']);
    $payment = $order->payments()->create([
        'transaction_id' => 'TXN-FAILED',
        'gateway' => 'zarinpal',
        'status' => 'failed',
        'amount' => 1000,
        'currency' => 'IRR',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/payments/{$payment->id}/verify")
        ->assertStatus(422);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
    $this->assertDatabaseCount('licenses', 0);
});
