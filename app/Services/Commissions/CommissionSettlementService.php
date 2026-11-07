<?php

namespace App\Services\Commissions;

use App\Context\TenantContext;
use App\Exceptions\Domain\CommissionNotSettleableException;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Repositories\Contracts\AffiliateRepositoryInterface;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CommissionSettlementService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected AffiliateRepositoryInterface $affiliateRepository,
        protected CommissionRepositoryInterface $commissionRepository
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getAffiliateLedger(string $affiliateId, array $filters = [], int $perPage = 20): array
    {
        $affiliate = $this->resolveTenantAffiliate($affiliateId);
        $totals = $this->commissionRepository->getTotalsByStatusForAffiliate($affiliate->id);
        $paginated = $this->commissionRepository->paginateByAffiliate($affiliate->id, $filters, $perPage);

        return [
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'email' => $affiliate->email,
            ],
            'totals' => $totals,
            'commissions' => $paginated,
        ];
    }

    /**
     * @param  list<string>  $commissionIds
     * @return array<string, mixed>
     */
    public function orchestrateCommissionSettlement(
        string $affiliateId,
        array $commissionIds,
        string $paymentReference,
        ?string $receiptUrl = null
    ): array {
        return DB::transaction(function () use ($affiliateId, $commissionIds, $paymentReference, $receiptUrl): array {
            $affiliate = $this->resolveTenantAffiliate($affiliateId);

            $commissions = $this->lockAndValidateCommissions($affiliate->id, $commissionIds);

            $paidAt = now();

            $this->persistSettlement($commissions, $paymentReference, $receiptUrl, $paidAt);

            return $this->calculateSettlementSummary($commissions, $paymentReference, $paidAt);
        });
    }

    public function resolveTenantAffiliate(string $affiliateId): Affiliate
    {
        $affiliate = $this->affiliateRepository->findById($affiliateId);

        if (! $affiliate) {
            throw new ModelNotFoundException("Afiliado con ID [{$affiliateId}] no encontrado.");
        }

        return $affiliate;
    }

    /**
     * @param  list<string>  $commissionIds
     * @return Collection<int, Commission>
     */
    public function lockAndValidateCommissions(string $affiliateId, array $commissionIds): Collection
    {
        $uniqueIds = array_values(array_unique($commissionIds));
        $commissions = $this->commissionRepository->findManyForAffiliateWithLock($affiliateId, $uniqueIds);

        if ($commissions->count() !== count($uniqueIds)) {
            throw new CommissionNotSettleableException(
                'Solo se pueden liquidar comisiones en estado APROBADA. Una o más comisiones seleccionadas no cumplen esta condición.'
            );
        }

        foreach ($commissions as $commission) {
            if ($commission->status !== 'APROBADA') {
                throw new CommissionNotSettleableException(
                    'Solo se pueden liquidar comisiones en estado APROBADA. Una o más comisiones seleccionadas no cumplen esta condición.'
                );
            }
        }

        return $commissions;
    }

    /**
     * @param  Collection<int, Commission>  $commissions
     */
    public function persistSettlement(
        Collection $commissions,
        string $reference,
        ?string $receiptUrl,
        CarbonInterface $paidAt
    ): void {
        $ids = [];
        foreach ($commissions as $commission) {
            $ids[] = $commission->id;
        }

        $this->commissionRepository->markManyAsPaid($ids, [
            'payment_reference' => $reference,
            'payment_receipt_url' => $receiptUrl,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * @param  Collection<int, Commission>  $commissions
     * @return array{settled_count: int, total_paid: float, status: string, payment_reference: string, paid_at: string}
     */
    public function calculateSettlementSummary(Collection $commissions, string $reference, CarbonInterface $paidAt): array
    {
        $total = 0.0;
        foreach ($commissions as $commission) {
            $total += (float) $commission->amount;
        }

        return [
            'settled_count' => $commissions->count(),
            'total_paid' => round($total, 2),
            'status' => 'PAGADA',
            'payment_reference' => $reference,
            'paid_at' => $paidAt->toISOString(),
        ];
    }
}
