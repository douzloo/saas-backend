<?php

namespace App\Services\Interfaces;

use App\Exceptions\AssignmentException;
use App\Models\Product;
use App\Models\ProductAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

interface ProductAssignmentServiceInterface
{
    /**
     * Assign a user to a product with a role.
     *
     * Enforces hierarchy: assigner must have equal or higher role.
     * Only system admins can assign 'admin' product role.
     * Self-assignment is validated in service layer.
     *
     * @param  User  $assigner  The user performing the assignment
     * @param  User  $user  The user to assign
     * @param  Product  $product  The product
     * @param  string  $role  The role to assign
     * @param  bool  $isPrimary  Whether this is the user's primary product
     *
     * @throws AssignmentException if role hierarchy violated
     */
    public function assign(
        User $assigner,
        User $user,
        Product $product,
        string $role,
        bool $isPrimary = false
    ): ProductAssignment;

    /**
     * Remove a user from a product.
     *
     * @param  User  $assigner  The user performing the removal
     * @param  User  $user  The user to remove
     * @param  Product  $product  The product
     *
     * @throws AssignmentException if assigner lacks permission
     */
    public function remove(User $assigner, User $user, Product $product): bool;

    /**
     * Check if a user has access to a product.
     */
    public function hasAccess(User $user, Product $product): bool;

    /**
     * Check if a user can manage another user's product assignment.
     */
    public function canManageAssignment(User $manager, ProductAssignment $target): bool;

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
     *
     * @param  User  $actor  The user attempting to assign the role
     * @param  string  $role  The role to assign
     * @param  User  $target  The user receiving the role
     * @param  Product  $product  The product context
     */
    public function canAssignRole(User $actor, string $role, User $target, Product $product): bool;

    /**
     * Get all products a user is assigned to.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function getUserProducts(User $user): Collection;

    /**
     * Get all staff assigned to a product.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function getProductStaff(Product $product): Collection;

    /**
     * Get user's role for a specific product.
     */
    public function getProductRole(User $user, Product $product): ?string;

    /**
     * Update a user's product assignment.
     *
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AssignmentException if role hierarchy violated
     */
    public function update(User $assigner, ProductAssignment $assignment, array $data): ProductAssignment;

    /**
     * Get a user's primary product assignment.
     */
    public function getPrimaryAssignment(User $user): ?ProductAssignment;

    /**
     * Set a product assignment as primary.
     */
    public function setPrimary(User $assigner, ProductAssignment $assignment): ProductAssignment;

    /**
     * Get product IDs a user has access to.
     *
     * @return array<int, int>
     */
    public function getUserProductIds(User $user): array;

    /**
     * Check if a user is assigned to any product.
     */
    public function hasAnyAssignment(User $user): bool;
}
