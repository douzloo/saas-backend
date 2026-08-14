<?php

use App\Models\Download;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Build a faked sandbox Zarinpal request + verify endpoint.
 *
 * @param  array{request?: array<string, mixed>, verify?: array<string, mixed>}  $overrides
 */
function fakeSandboxZarinpal(array $overrides = []): void
{
    config()->set('payments.gateways.zarinpal.merchant_id', 'test-merchant');
    config()->set('payments.gateways.zarinpal.sandbox', true);
    config()->set('payments.redirect_base_url', 'http://localhost:3000');

    $request = array_merge([
        'data' => ['authority' => 'A000000000000000000000000000123456', 'fee' => 0, 'fee_type' => 'Merchant'],
        'errors' => [],
        'code' => 100,
        'message' => 'Success',
    ], $overrides['request'] ?? []);

    $verify = array_merge([
        'data' => ['code' => 100, 'message' => 'Verified', 'ref_id' => 9876543210, 'card_hash' => 'x', 'card_pan' => 'y', 'fee' => 0, 'fee_type' => 'Merchant'],
        'errors' => [],
        'code' => 100,
        'message' => 'Success',
    ], $overrides['verify'] ?? []);

    Http::fake([
        'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response($request, $overrides['request_code'] ?? 200),
        'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response($verify, $overrides['verify_code'] ?? 200),
        'sandbox.zarinpal.com/*' => Http::response([], 500),
    ]);
}

/** @return array{user: User, order: Order, payment: Payment, product: Product} */
function zarinpalScenario(?array $overrides = null): array
{
    fakeSandboxZarinpal($overrides ?? []);

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'price' => 100000,
        'requires_activation' => true,
        'trial_days' => 0,
        'max_domains' => 3,
    ]);

    $order = Order::factory()->for($user)->create(['status' => 'pending', 'total' => 110000]);
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100000,
        'total_price' => 100000,
    ]);

    $payment = Payment::factory()->for($order)->create([
        'transaction_id' => 'TXN-ZARINPAL-TEST',
        'gateway' => 'zarinpal',
        'status' => 'processing',
        'amount' => 110000,
        'authority' => 'A000000000000000000000000000123456',
        'gateway_response' => ['code' => 100, 'data' => ['authority' => 'A000000000000000000000000000123456']],
    ]);

    return compact('user', 'order', 'payment', 'product');
}

it('creates a zarinpal payment via the API and returns the gateway URL', function () {
    fakeSandboxZarinpal();

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100000, 'requires_activation' => true]);

    $order = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
        ->json('data');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/pay", ['gateway' => 'zarinpal'])
        ->assertOk();

    $response->assertJsonStructure(['payment_id', 'transaction_id', 'authority', 'amount', 'gateway', 'redirect_url']);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'payment/request.json')
            && $request['merchant_id'] === 'test-merchant'
            && $request['amount'] === 11000; // 110,000 IRR / 10 = 11,000 Toman
    });

    $payment = Payment::find($response->json('payment_id'));
    expect($payment->authority)->toBe('A000000000000000000000000000123456');
    expect($payment->status)->toBe('pending');
    expect($payment->gateway_response)->toBeArray();
    expect($response->json('redirect_url'))->toContain('sandbox.zarinpal.com/pg/StartPay/');
});

it('marks an opened payment as failed when the gateway rejects the create request', function () {
    fakeSandboxZarinpal(['request' => [
        'data' => [],
        'errors' => [['code' => -9, 'message' => 'Invalid merchant']],
        'code' => -9,
        'message' => 'Invalid merchant',
    ]]);

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100000]);

    $order = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id]]])
        ->json('data');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order['id']}/pay", ['gateway' => 'zarinpal'])
        ->assertStatus(422);

    expect(Payment::where('order_id', $order['id'])->first()->status)->toBe('failed');
});

it('rejects unimplemented gateways', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => 'pending', 'total' => 1000]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/pay", ['gateway' => 'mellat'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'درگاه پرداخت موردنظر هنوز فعال نشده است.');
});

it('successful callback verifies, completes the order and issues a license', function () {
    $scenario = zarinpalScenario();

    $this->getJson('/api/payment/callback/zarinpal?Authority='.$scenario['payment']->authority.'&Status=OK')
        ->assertRedirect();

    $this->assertDatabaseHas('payments', [
        'id' => $scenario['payment']->id,
        'status' => 'completed',
        'ref_id' => '9876543210',
    ]);
    $this->assertDatabaseHas('orders', ['id' => $scenario['order']->id, 'status' => 'completed']);
    $this->assertDatabaseCount('licenses', 1);
});

it('failed callback (Status != OK) marks the payment as failed', function () {
    $scenario = zarinpalScenario();

    $this->getJson('/api/payment/callback/zarinpal?Authority='.$scenario['payment']->authority.'&Status=NOK')
        ->assertRedirect()
        ->assertRedirectContains('status=failed');

    $this->assertDatabaseHas('payments', [
        'id' => $scenario['payment']->id,
        'status' => 'failed',
        'failed_at' => now()->toDateTimeString(),
    ]);
    $this->assertDatabaseCount('licenses', 0);
});

it('rejects a callback with an unknown authority', function () {
    fakeSandboxZarinpal();

    $this->getJson('/api/payment/callback/zarinpal?Authority=UNKNOWN&Status=OK')
        ->assertRedirect()
        ->assertRedirectContains('status=failed');

    $this->assertDatabaseCount('licenses', 0);
});

it('duplicate callback does not create a second license', function () {
    $scenario = zarinpalScenario();

    $url = '/api/payment/callback/zarinpal?Authority='.$scenario['payment']->authority.'&Status=OK';

    $this->getJson($url)->assertRedirect();
    $this->getJson($url)->assertRedirect();

    $this->assertDatabaseCount('licenses', 1);
    $this->assertDatabaseHas('payments', ['id' => $scenario['payment']->id, 'status' => 'completed']);
});

it('cannot replay a callback for a failed payment', function () {
    $scenario = zarinpalScenario([
        'request' => ['data' => ['authority' => 'A000000000000000000000000000123456', 'fee' => 0, 'fee_type' => 'Merchant']],
        'verify' => [
            'data' => ['code' => -101, 'message' => 'Not verified', 'ref_id' => null],
            'errors' => [],
            'code' => -101,
            'message' => 'Not verified',
        ],
    ]);

    $url = '/api/payment/callback/zarinpal?Authority='.$scenario['payment']->authority.'&Status=OK';

    // First callback: verify rejects (-101) -> payment failed.
    $this->getJson($url)
        ->assertRedirect()
        ->assertRedirectContains('status=failed');

    // Second attempt must not complete — the payment is already failed.
    $this->getJson($url)
        ->assertRedirect()
        ->assertRedirectContains('status=failed');

    $this->assertDatabaseHas('payments', ['id' => $scenario['payment']->id, 'status' => 'failed']);
    $this->assertDatabaseCount('licenses', 0);
});

it('verifies a payment through the API and completes the order', function () {
    $scenario = zarinpalScenario();

    $response = $this->actingAs($scenario['user'], 'sanctum')
        ->postJson("/api/orders/{$scenario['order']->id}/payments/{$scenario['payment']->id}/verify")
        ->assertOk();

    $response->assertJsonPath('data.status', 'completed');
    $this->assertDatabaseHas('payments', ['id' => $scenario['payment']->id, 'status' => 'completed']);
    $this->assertDatabaseHas('orders', ['id' => $scenario['order']->id, 'status' => 'completed']);
    $this->assertDatabaseCount('licenses', 1);
});

it('rejects API verification when the gateway rejects the payment', function () {
    $scenario = zarinpalScenario([
        'verify' => [
            'data' => ['code' => -101, 'message' => 'Not paid'],
            'errors' => [],
            'code' => -101,
            'message' => 'Not paid',
        ],
    ]);

    $this->actingAs($scenario['user'], 'sanctum')
        ->postJson("/api/orders/{$scenario['order']->id}/payments/{$scenario['payment']->id}/verify")
        ->assertStatus(422);

    $this->assertDatabaseHas('payments', ['id' => $scenario['payment']->id, 'status' => 'failed']);
    $this->assertDatabaseCount('licenses', 0);
});

it('is idempotent when verifying an already completed payment through the API', function () {
    $scenario = zarinpalScenario();

    $this->actingAs($scenario['user'], 'sanctum')
        ->postJson("/api/orders/{$scenario['order']->id}/payments/{$scenario['payment']->id}/verify")
        ->assertOk();

    $this->actingAs($scenario['user'], 'sanctum')
        ->postJson("/api/orders/{$scenario['order']->id}/payments/{$scenario['payment']->id}/verify")
        ->assertOk();

    // Only the first verify contacted the provider; the re-verify was a no-op.
    Http::assertSentCount(1);

    $this->assertDatabaseCount('licenses', 1);
});

it('grant license holder access to download a gated file', function () {
    $scenario = zarinpalScenario();

    $download = Download::factory()->create(['product_id' => $scenario['product']->id]);

    $this->actingAs($scenario['user'], 'sanctum')
        ->postJson("/api/orders/{$scenario['order']->id}/payments/{$scenario['payment']->id}/verify")
        ->assertOk();

    $this->actingAs($scenario['user'], 'sanctum')
        ->getJson("/api/downloads/{$download->id}")
        ->assertOk()
        ->assertJsonPath('download_url', $download->download_url);
});
