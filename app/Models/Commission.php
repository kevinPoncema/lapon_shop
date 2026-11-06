<?php

namespace App\Models;

use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string $affiliate_id
 * @property string $coupon_id
 * @property string $amount
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $payment_reference
 * @property string|null $payment_receipt_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'order_id',
        'affiliate_id',
        'coupon_id',
        'amount',
        'status',
        'paid_at',
        'payment_reference',
        'payment_receipt_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The tenant that owns the commission.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * The order that originated the commission.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * The affiliate beneficiary of this commission.
     *
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /**
     * The coupon associated with this commission.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
