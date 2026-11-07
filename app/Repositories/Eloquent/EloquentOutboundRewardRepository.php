<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Repositories\Contracts\OutboundRewardRepositoryInterface;
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OutboundReward
    {
        $data['tenant_id'] = $this->tenantContext->id();

        return OutboundReward::query()->create($data);
    }
}
