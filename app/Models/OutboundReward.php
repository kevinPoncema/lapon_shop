<?php

namespace App\Models;

use Database\Factories\OutboundRewardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string $partner_store_id
 * @property string $code
 * @property string $status
 * @property Carbon $expires_at
 * @property Carbon|null $redeemed_at
 * @property Carbon|null $email_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OutboundReward extends Model
{
    /** @use HasFactory<OutboundRewardFactory> */
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'outbound_rewards';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'order_id',
        'partner_store_id',
        'code',
        'status',
        'expires_at',
        'redeemed_at',
        'email_sent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    /**
     * The tenant that issued the voucher.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * The order that triggered this reward.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * The partner store where the voucher can be redeemed.
     *
     * @return BelongsTo<PartnerStore, $this>
     */
    public function partnerStore(): BelongsTo
    {
        return $this->belongsTo(PartnerStore::class, 'partner_store_id');
    }
}
