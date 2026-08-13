<?php

namespace App\Services;

use App\Exceptions\AssignmentException;
use App\Models\Product;
use App\Models\ProductAssignment;
use App\Models\User;
use App\Services\Interfaces\ProductAssignmentServiceInterface;
use Illuminate\Support\Collection;

class ProductAssignmentService implements ProductAssignmentServiceInterface
{
    public function assign(
        User $assigner,
        User $user,
        Product $product,
        string $role,
        bool $isPrimary = false
    ): ProductAssignment {
        // Validate role
        if (! ProductAssignment::isValidRole($role)) {
            throw AssignmentException::invalidRole($role);
        }

        // Check if assigner can assign this role to this user
        if (! $this->canAssignRole($assigner, $role, $user, $product)) {
            $assignerRole = $this->getProductRole($assigner, $product);
            throw AssignmentException::roleHierarchyViolation(
                $assignerRole ?? 'none',
                $role
            );
        }

        // Check if user already assigned
        $existing = ProductAssignment::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            // Update existing assignment
            return $this->update($assigner, $existing, [
                'role' => $role,
                'is_primary' => $isPrimary,
            ]);
        }

        // Create new assignment
        $assignment = ProductAssignment::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'role' => $role,
            'is_primary' => $isPrimary,
            'assigned_at' => now(),
        ]);

        // If primary, reset other primary assignments
        if ($isPrimary) {
            $this->resetOtherPrimaryAssignments($user->id, $assignment->id);
        }

        return $assignment;
    }

    public function remove(User $assigner, User $user, Product $product): bool
    {
        $assignment = ProductAssignment::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if (! $assignment) {
            throw AssignmentException::notAssignedToProduct($user->id, $product->id);
        }

        // Check if assigner can manage this assignment
        if (! $this->canManageAssignment($assigner, $assignment)) {
            throw AssignmentException::roleHierarchyViolation(
                $this->getProductRole($assigner, $product) ?? 'none',
                $assignment->role
            );
        }

        return $assignment->delete();
    }

    public function hasAccess(User $user, Product $product): bool
    {
        // System admins always have access
        if ($user->isAdmin()) {
            return true;
        }

        return ProductAssignment::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();
    }

    public function canManageAssignment(User $manager, ProductAssignment $target): bool
    {
        // System admins can manage any assignment
        if ($manager->isAdmin()) {
            return true;
        }

        // Get manager's role for this product
        $managerAssignment = ProductAssignment::where('user_id', $manager->id)
            ->where('product_id', $target->product_id)
            ->first();

        if (! $managerAssignment) {
            return false;
        }

        // Manager's role level must be >= target's role level
        return $managerAssignment->role_level >= $target->role_level;
    }

    /**
     * Check if a user can assign a specific role to a target user.
     *
     * Business Rules:
     *
     * 1. System Admin:
     *    - Can assign any product role
     *    - Can assign admin role
     *    - Can assign to self if explicitly allowed (no restriction)
     *
     * 2. Product Manager:
     *    - Cannot assign admin role
     *    - Cannot modify users outside allowed product scope
     *    - Cannot self-assign elevated permissions (role > current role)
     *
     * 3. Staff:
     *    - Cannot assign roles (only admins and managers can assign)
     *
     * 4. Self-Assignment:
     *    - Must be validated inside service layer
     *    - Controllers must not contain this business rule
     *    - System admins can self-assign any role
     *    - Managers cannot self-assign roles higher than their current role
     *    - Staff cannot self-assign at all
     */
    public function canAssignRole(User $actor, string $role, User $target, Product $product): bool
    {
        $isSelfAssignment = $actor->id === $target->id;

        // Rule 1: System Admin
        if ($actor->isAdmin()) {
            // System admins can assign any role
            // System admins can self-assign any role
            return true;
        }

        // Get actor's product role
        $actorRole = $this->getProductRole($actor, $product);

        // Rule 3: Staff (no product assignment or viewer role) cannot assign roles
        if ($actorRole === null || $actorRole === ProductAssignment::ROLE_VIEWER) {
            return false;
        }

        // Rule 2: Product Manager
        $actorLevel = ProductAssignment::getRoleLevel($actorRole);
        $targetLevel = ProductAssignment::getRoleLevel($role);

        // Managers cannot assign admin role
        if ($role === ProductAssignment::ROLE_ADMIN) {
            return false;
        }

        // Actor's role level must be >= target role level
        if ($actorLevel < $targetLevel) {
            return false;
        }

        // Rule 4: Self-Assignment Validation
        if ($isSelfAssignment) {
            // Managers cannot self-assign elevated permissions
            // (role higher than their current role)
            if ($targetLevel > $actorLevel) {
                return false;
            }

            // Managers can self-assign equal or lower roles
            return true;
        }

        // Non-self assignment: check if target is within actor's product scope
        // (Actor must have assignment for this product, which is already checked above)

        return true;
    }

    public function getUserProducts(User $user): Collection
    {
        return Product::whereHas('assignments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();
    }

    public function getProductStaff(Product $product): Collection
    {
        return User::whereHas('assignments', function ($query) use ($product) {
            $query->where('product_id', $product->id);
        })->with(['assignments' => function ($query) use ($product) {
            $query->where('product_id', $product->id);
        }])->get();
    }

    public function getProductRole(User $user, Product $product): ?string
    {
        $assignment = ProductAssignment::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        return $assignment?->role;
    }

    public function update(User $assigner, ProductAssignment $assignment, array $data): ProductAssignment
    {
        // Check if role is being changed
        if (isset($data['role']) && $data['role'] !== $assignment->role) {
            // Check if assigner can assign this role to the target user
            if (! $this->canAssignRole($assigner, $data['role'], $assignment->user, $assignment->product)) {
                throw AssignmentException::roleHierarchyViolation(
                    $this->getProductRole($assigner, $assignment->product) ?? 'none',
                    $data['role']
                );
            }
        }

        $assignment->update($data);

        // If primary, reset other primary assignments
        if (isset($data['is_primary']) && $data['is_primary']) {
            $this->resetOtherPrimaryAssignments($assignment->user_id, $assignment->id);
        }

        return $assignment;
    }

    public function getPrimaryAssignment(User $user): ?ProductAssignment
    {
        return ProductAssignment::where('user_id', $user->id)
            ->primary()
            ->first();
    }

    public function setPrimary(User $assigner, ProductAssignment $assignment): ProductAssignment
    {
        return $this->update($assigner, $assignment, ['is_primary' => true]);
    }

    public function getUserProductIds(User $user): array
    {
        return ProductAssignment::where('user_id', $user->id)
            ->pluck('product_id')
            ->toArray();
    }

    public function hasAnyAssignment(User $user): bool
    {
        return ProductAssignment::where('user_id', $user->id)->exists();
    }

    /**
     * Reset other primary assignments for a user.
     */
    protected function resetOtherPrimaryAssignments(int $userId, int $exceptAssignmentId): void
    {
        ProductAssignment::where('user_id', $userId)
            ->where('id', '!=', $exceptAssignmentId)
            ->update(['is_primary' => false]);
    }
}
