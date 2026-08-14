<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Per-gateway settings consumed by the PaymentGatewayManager. Only
    | "zarinpal" is implemented at the moment; the remaining gateways are
    | recognized by the platform and throw NotImplementedException.
    |
    */

    'gateways' => [

        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => (bool) env('ZARINPAL_SANDBOX', true),
            'callback_url' => env('ZARINPAL_CALLBACK_URL'),
            'currency' => env('ZARINPAL_CURRENCY', 'IRR'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend redirect base
    |--------------------------------------------------------------------------
    |
    | Absolute base URL of the frontend. The Zarinpal callback controller
    | redirects the buyer's browser here (e.g. /checkout/result?status=…).
    |
    */

    'redirect_base_url' => env('FRONTEND_URL', config('app.url')),

];
