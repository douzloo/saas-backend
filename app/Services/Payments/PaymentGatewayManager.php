<?php

namespace App\Services\Payments;

use App\Exceptions\NotImplementedException;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\ZarinpalGateway;

/**
 * Resolves a payment gateway implementation by its canonical name.
 *
 * All known gateway names resolve through this manager. Only "zarinpal" has a
 * concrete implementation at the moment; every other known gateway throws
 * NotImplementedException so callers fail loudly instead of silently
 * producing broken payment links.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>|null> */
    private const GATEWAYS = [
        'zarinpal' => ZarinpalGateway::class,
        'mellat' => null,
        'saman' => null,
        'pay_ir' => null,
        'idpay' => null,
    ];

    public function gateway(string $name): PaymentGatewayInterface
    {
        $class = self::GATEWAYS[$name] ?? null;

        if ($class === null) {
            throw new NotImplementedException(
                sprintf('Payment gateway "%s" is recognized but not implemented yet.', $name)
            );
        }

        return app($class);
    }
}
