<?php

use App\Models\Product;
use App\Models\User;

it('returns sane status codes for all public routes', function () {
    Product::factory()->count(2)->create();

    $routes = [
        ['GET', '/api/products'],
        ['GET', '/api/products/categories'],
        ['GET', '/api/settings'],
        ['GET', '/api/pages'],
        ['GET', '/api/downloads'],
        ['GET', '/api/downloads/latest'],
        ['GET', '/api/tickets'],
        ['GET', '/api/orders'],
        ['GET', '/api/invoices'],
        ['GET', '/api/payments'],
        ['GET', '/api/crm/leads'],
        ['GET', '/api/auth/user'],
        ['GET', '/api/admin/dashboard'],
        ['GET', '/api/admin/billing/metrics'],
    ];

    foreach ($routes as [$method, $uri]) {
        $response = $this->getJson($uri);
        $code = $response->status();
        expect(in_array($code, [200, 401, 403, 404, 405], true))
            ->toBeTrue("$method $uri returned $code");
    }
});

it('returns sane status codes for authenticated customer routes', function () {
    $user = User::factory()->create();
    Product::factory()->count(2)->create();

    $routes = [
        ['GET', '/api/portal/dashboard'],
        ['GET', '/api/portal/downloads'],
        ['GET', '/api/tickets'],
        ['GET', '/api/orders'],
        ['GET', '/api/invoices'],
        ['GET', '/api/payments'],
        ['GET', '/api/crm/leads'],
    ];

    foreach ($routes as [$method, $uri]) {
        $response = $this->actingAs($user, 'sanctum')->getJson($uri);
        $code = $response->status();
        expect(in_array($code, [200, 403, 404], true))
            ->toBeTrue("$method $uri returned $code");
    }
});

it('returns 403 for customer on admin routes', function () {
    $user = User::factory()->create();

    foreach (['/api/admin/dashboard', '/api/admin/billing/metrics'] as $uri) {
        $this->actingAs($user, 'sanctum')->getJson($uri)->assertForbidden();
    }
});

it('returns 403 for customer on staff-only admin routes', function () {
    $user = User::factory()->create();

    foreach (['/api/admin/downloads', '/api/admin/downloads/stats'] as $uri) {
        $this->actingAs($user, 'sanctum')->getJson($uri)->assertForbidden();
    }
});
