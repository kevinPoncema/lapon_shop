<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $country_code
 * @property string $currency_code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'country_code',
        'currency_code',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active tenants.
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
     * Resolve a tenant by normalized slug using a plain-array cache payload
     * compatible with `cache.serializable_classes = false`.
     */
    public static function findCachedBySlug(string $slug): ?self
    {
        $normalized = Str::lower(trim($slug));

        if ($normalized === '') {
            return null;
        }

        $cacheKey = "tenant:slug:{$normalized}";
        $cached = Cache::get($cacheKey);

        if ($cached instanceof self) {
            return $cached;
        }

        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return (new self)->newFromBuilder($cached);
        }

        $tenant = self::query()
            ->where('slug', $normalized)
            ->first();

        if ($tenant === null) {
            return null;
        }

        /** @var array<string, mixed> $attributes */
        $attributes = $tenant->getAttributes();

        Cache::put($cacheKey, $attributes, 3600);

        return $tenant;
    }

    /**
     * Configuration associated with the tenant.
     *
     * @return HasOne<TenantConfig, $this>
     */
    public function config(): HasOne
    {
        return $this->hasOne(TenantConfig::class, 'tenant_id');
    }

    /**
     * Users belonging to the tenant.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * Categories defined by the tenant.
     *
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'tenant_id');
    }

    /**
     * Products belonging to the tenant.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tenant_id');
    }

    /**
     * Product variants belonging to the tenant.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'tenant_id');
    }

    /**
     * Promotions configured by the tenant.
     *
     * @return HasMany<Promotion, $this>
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'tenant_id');
    }

    /**
     * Affiliates registered for the tenant.
     *
     * @return HasMany<Affiliate, $this>
     */
    public function affiliates(): HasMany
    {
        return $this->hasMany(Affiliate::class, 'tenant_id');
    }

    /**
     * Coupons issued by the tenant.
     *
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'tenant_id');
    }

    /**
     * Orders processed by the tenant.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'tenant_id');
    }

    /**
     * Order items belonging to the tenant.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'tenant_id');
    }

    /**
     * Commissions generated under the tenant.
     *
     * @return HasMany<Commission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'tenant_id');
    }

    /**
     * Partner stores associated with the tenant.
     *
     * @return HasMany<PartnerStore, $this>
     */
    public function partnerStores(): HasMany
    {
        return $this->hasMany(PartnerStore::class, 'tenant_id');
    }

    /**
     * Outbound rewards emitted under the tenant.
     *
     * @return HasMany<OutboundReward, $this>
     */
    public function outboundRewards(): HasMany
    {
        return $this->hasMany(OutboundReward::class, 'tenant_id');
    }
}
