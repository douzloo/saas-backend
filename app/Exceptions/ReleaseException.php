<?php

namespace App\Exceptions;

use Exception;

class ReleaseException extends Exception
{
    protected ?string $reason = null;

    public static function versionAlreadyExists(string $version, int $productId): self
    {
        $e = new self("Version {$version} already exists for product {$productId}.");
        $e->reason = 'version_exists';

        return $e;
    }

    public static function cannotPublish(string $status): self
    {
        $e = new self("Release cannot be published while status is '{$status}'.");
        $e->reason = 'cannot_publish';

        return $e;
    }

    public static function cannotDeprecate(string $status): self
    {
        $e = new self("Release cannot be deprecated while status is '{$status}'.");
        $e->reason = 'cannot_deprecate';

        return $e;
    }

    public static function cannotRollback(string $status): self
    {
        $e = new self("Release cannot be rolled back while status is '{$status}'.");
        $e->reason = 'cannot_rollback';

        return $e;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
