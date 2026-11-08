<?php

namespace App\Repositories\Contracts;

use App\Models\OutboundReward;
use App\Models\PartnerStore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface OutboundRewardRepositoryInterface
{
    /**
     * @return Collection<int, PartnerStore>
     */
    public function findActivePartnerStoresForTenant(): Collection;

    public function existsByCode(string $code): bool;

    public function existsForOrderAndPartner(string $orderId, string $partnerStoreId): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OutboundReward;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createReward(array $attributes): OutboundReward;

    public function findByIdWithRelations(string $rewardId): ?OutboundReward;

    public function findDetailedByCodeForPartner(string $partnerStoreId, string $code): ?OutboundReward;

    public function findByCodeForPartnerWithLock(string $partnerStoreId, string $code): ?OutboundReward;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(OutboundReward $reward, array $attributes): OutboundReward;

    public function markEmailSent(OutboundReward $reward, Carbon $sentAt): OutboundReward;
}
