<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\AffiliateFactory;
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
 * @property string $email
 * @property string $phone
 * @property string $commission_type
 * @property string $commission_value
 * @property array<string, mixed> $payment_info
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Affiliate extends Model
{
    /** @use HasFactory<AffiliateFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'commission_type',
        'commission_value',
        'payment_info',
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
            'commission_value' => 'decimal:2',
            'payment_info' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active affiliates.
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
     * Coupons attributed to this affiliate.
     *
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'affiliate_id');
    }

    /**
     * Commissions earned by this affiliate.
     *
     * @return HasMany<Commission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'affiliate_id');
    }
}
