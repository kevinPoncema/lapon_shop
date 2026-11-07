<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $type
 * @property string $discount_type
 * @property string $discount_value
 * @property array<string, mixed> $conditions
 * @property bool $allow_stacking
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'discount_type',
        'discount_value',
        'conditions',
        'allow_stacking',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'conditions' => 'array',
            'allow_stacking' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active promotions.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Coupons generated from or linked to this promotion.
     *
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'promotion_id');
    }

    /**
     * Orders where this promotion was applied automatically.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'applied_promotion_id');
    }
}
