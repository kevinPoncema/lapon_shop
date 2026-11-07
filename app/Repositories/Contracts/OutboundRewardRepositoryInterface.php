<?php

namespace App\Repositories\Contracts;

use App\Models\OutboundReward;
use App\Models\PartnerStore;
use Illuminate\Database\Eloquent\Collection;

interface OutboundRewardRepositoryInterface
{
    /**
     * @return Collection<int, PartnerStore>
     */
    public function findActivePartnerStoresForTenant(): Collection;

    public function existsByCode(string $code): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OutboundReward;
}
