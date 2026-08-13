<?php

namespace App\Exceptions;

use Exception;

class LicenseException extends Exception
{
    protected ?string $reason = null;

    public static function missingIdentifier(string $identifier, string $strategy): self
    {
        $e = new self("Missing required activation identifier: {$identifier} for strategy '{$strategy}'.");
        $e->reason = 'missing_identifier';

        return $e;
    }

    public static function invalidStrategy(string $strategy): self
    {
        $e = new self("Unsupported activation strategy: {$strategy}.");
        $e->reason = 'invalid_strategy';

        return $e;
    }

    public static function invalidFingerprint(string $fingerprint): self
    {
        $e = new self("Invalid machine fingerprint format: {$fingerprint}.");
        $e->reason = 'invalid_fingerprint';

        return $e;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
