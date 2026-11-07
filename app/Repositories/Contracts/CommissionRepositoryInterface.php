<?php

namespace App\Repositories\Contracts;

use App\Models\Commission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CommissionRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Commission;

    public function findByOrderId(string $orderId): ?Commission;

    public function updateStatus(Commission $commission, string $status): Commission;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Commission>
     */
    public function paginateByAffiliate(string $affiliateId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * @return array{retained: float, approved: float, paid: float, voided: float}
     */
    public function getTotalsByStatusForAffiliate(string $affiliateId): array;

    /**
     * @param  list<string>  $commissionIds
     * @return Collection<int, Commission>
     */
    public function findManyForAffiliateWithLock(string $affiliateId, array $commissionIds): Collection;

    /**
     * @param  list<string>  $commissionIds
     * @param  array<string, mixed>  $settlementData
     */
    public function markManyAsPaid(array $commissionIds, array $settlementData): int;
}
