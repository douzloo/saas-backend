<?php

namespace App\Models;

use Database\Factories\LeadStageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadStage extends Model
{
    /** @use HasFactory<LeadStageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'color',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<LeadStage>  $query
     * @return Builder<LeadStage>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<LeadStage>  $query
     * @return Builder<LeadStage>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
