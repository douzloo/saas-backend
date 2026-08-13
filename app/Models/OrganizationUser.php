<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $role
 * @property array<string>|null $permissions
 */
class OrganizationUser extends Pivot
{
    protected $table = 'organization_user';

    protected $casts = [
        'permissions' => 'array',
    ];
}
