<?php

namespace App\Models;

use Database\Factories\TenantConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $tenant_id
 * @property array<string, mixed> $branding
 * @property array<string, mixed> $contact_info
 * @property list<array<string, mixed>> $shipping_zones
 * @property list<array<string, mixed>> $metadata_schema
 * @property list<array<string, mixed>> $filter_config
 * @property array<string, mixed> $outbound_reward_rules
 * @property Carbon|null $updated_at
 */
class TenantConfig extends Model
{
    /** @use HasFactory<TenantConfigFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_configs';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'tenant_id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    public const CREATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branding',
        'contact_info',
        'shipping_zones',
        'metadata_schema',
        'filter_config',
        'outbound_reward_rules',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'contact_info' => 'array',
            'shipping_zones' => 'array',
            'metadata_schema' => 'array',
            'filter_config' => 'array',
            'outbound_reward_rules' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The tenant that owns the configuration.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
