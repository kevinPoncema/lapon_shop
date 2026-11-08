<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\PartnerStoreFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $portal_email
 * @property string $portal_password
 * @property string $benefit_title
 * @property string $terms_conditions
 * @property array<string, mixed> $contact_info
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PartnerStore extends Authenticatable
{
    /** @use HasFactory<PartnerStoreFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'partner_stores';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'portal_email',
        'portal_password',
        'benefit_title',
        'terms_conditions',
        'contact_info',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'portal_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal_password' => 'hashed',
            'contact_info' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'portal_password';
    }

    /**
     * Scope a query to only include active partner stores.
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
     * Outbound rewards redeemable at this partner store.
     *
     * @return HasMany<OutboundReward, $this>
     */
    public function outboundRewards(): HasMany
    {
        return $this->hasMany(OutboundReward::class, 'partner_store_id');
    }
}
