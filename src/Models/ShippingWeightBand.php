<?php

namespace Laratables\Shipping\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ShippingWeightBand extends Model
{
    use HasFactory;

    protected $fillable = ['max_kg', 'rate_per_kg', 'label', 'sort_order', 'active'];

    protected $casts = [
        'max_kg'      => 'float',
        'rate_per_kg' => 'decimal:4',
        'active'      => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('max_kg');
    }

    public static function toCalculatorBands(): array
    {
        return static::active()
            ->get(['max_kg', 'rate_per_kg'])
            ->map(fn ($band) => [
                'max_kg'      => (float) $band->max_kg,
                'rate_per_kg' => (float) $band->rate_per_kg,
            ])
            ->all();
    }
}
