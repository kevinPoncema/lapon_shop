<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Repositories\Contracts\OutboundRewardRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class EloquentOutboundRewardRepository implements OutboundRewardRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * @return Collection<int, PartnerStore>
     */
    public function findActivePartnerStoresForTenant(): Collection
    {
        return PartnerStore::query()
            ->where('is_active', true)
            ->get();
    }

    public function existsByCode(string $code): bool
    {
        return OutboundReward::query()->where('code', $code)->exists();
    }

    public function existsForOrderAndPartner(string $orderId, string $partnerStoreId): bool
    {
        return OutboundReward::query()
            ->where('order_id', $orderId)
            ->where('partner_store_id', $partnerStoreId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OutboundReward
    {
        $data['tenant_id'] = $this->tenantContext->id();

        return OutboundReward::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createReward(array $attributes): OutboundReward
    {
        return $this->create($attributes);
    }

    public function findByIdWithRelations(string $rewardId): ?OutboundReward
    {
        return OutboundReward::query()
            ->with(['order.tenant', 'partnerStore'])
            ->find($rewardId);
    }

    public function findDetailedByCodeForPartner(string $partnerStoreId, string $code): ?OutboundReward
    {
        return OutboundReward::query()
            ->with(['order.tenant', 'partnerStore'])
            ->where('partner_store_id', $partnerStoreId)
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();
    }

    public function findByCodeForPartnerWithLock(string $partnerStoreId, string $code): ?OutboundReward
    {
        return OutboundReward::query()
            ->with(['order.tenant', 'partnerStore'])
            ->where('partner_store_id', $partnerStoreId)
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(OutboundReward $reward, array $attributes): OutboundReward
    {
        $reward->update($attributes);

        return $reward->fresh();
    }

    public function markEmailSent(OutboundReward $reward, Carbon $sentAt): OutboundReward
    {
        $reward->update(['email_sent_at' => $sentAt]);

        return $reward->fresh();
    }
}
