<?php

namespace App\Exceptions;

use Exception;

class AssignmentException extends Exception
{
    protected ?string $reason = null;

    public static function invalidRole(string $role): self
    {
        $e = new self("Invalid product role: {$role}.");
        $e->reason = 'invalid_role';

        return $e;
    }

    public static function roleHierarchyViolation(string $assignerRole, string $targetRole): self
    {
        $e = new self("Role hierarchy violation: '{$assignerRole}' cannot assign '{$targetRole}'.");
        $e->reason = 'role_hierarchy_violation';

        return $e;
    }

    public static function notAssignedToProduct(int $userId, int $productId): self
    {
        $e = new self("User {$userId} is not assigned to product {$productId}.");
        $e->reason = 'not_assigned';

        return $e;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
