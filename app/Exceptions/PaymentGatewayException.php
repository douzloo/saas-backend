<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a payment provider rejects a transaction or is unreachable.
 */
class PaymentGatewayException extends Exception
{
    /** @var array<string, mixed>|null */
    protected ?array $context = null;

    /**
     * @param  array<string, mixed>|null  $context
     */
    public static function withContext(string $message, ?array $context = null): self
    {
        $e = new self($message);
        $e->context = $context;

        return $e;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }
}
