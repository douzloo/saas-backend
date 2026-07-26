<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
    ];


    public function owner()
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }


    public function users()
    {
       return $this->belongsToMany(User::class)
	->using(OrganizationUser::class)
        ->withPivot([
            'role',
            'permissions'
        ])
        ->withTimestamps();
    }
}
