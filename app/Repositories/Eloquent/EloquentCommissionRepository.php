<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Commission;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentCommissionRepository implements CommissionRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Commission
    {
        $data['tenant_id'] = $this->tenantContext->id();

        return Commission::query()->create($data);
    }

    public function findByOrderId(string $orderId): ?Commission
    {
        return Commission::query()->where('order_id', $orderId)->first();
    }

    public function updateStatus(Commission $commission, string $status): Commission
    {
        $commission->update(['status' => $status]);

        return $commission->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Commission>
     */
    public function paginateByAffiliate(string $affiliateId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Commission::query()
            ->with(['order', 'coupon'])
            ->where('affiliate_id', $affiliateId)
            ->when(! empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', (string) $filters['status']);
            })
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return array{retained: float, approved: float, paid: float, voided: float}
     */
    public function getTotalsByStatusForAffiliate(string $affiliateId): array
    {
        $sums = Commission::query()
            ->where('affiliate_id', $affiliateId)
            ->selectRaw('status, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'retained' => (float) ($sums['RETENIDA'] ?? 0.0),
            'approved' => (float) ($sums['APROBADA'] ?? 0.0),
            'paid' => (float) ($sums['PAGADA'] ?? 0.0),
            'voided' => (float) ($sums['ANULADA'] ?? 0.0),
        ];
    }

    /**
     * @param  list<string>  $commissionIds
     * @return Collection<int, Commission>
     */
    public function findManyForAffiliateWithLock(string $affiliateId, array $commissionIds): Collection
    {
        if (empty($commissionIds)) {
            return new Collection;
        }

        return Commission::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('id', $commissionIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  list<string>  $commissionIds
     * @param  array<string, mixed>  $settlementData
     */
    public function markManyAsPaid(array $commissionIds, array $settlementData): int
    {
        if (empty($commissionIds)) {
            return 0;
        }

        $settlementData['status'] = 'PAGADA';
        $settlementData['paid_at'] = $settlementData['paid_at'] ?? now();

        return Commission::query()
            ->whereIn('id', $commissionIds)
            ->update($settlementData);
    }
}
