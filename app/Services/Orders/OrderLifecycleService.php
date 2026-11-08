<?php

namespace App\Services\Orders;

use App\Context\TenantContext;
use App\Exceptions\Domain\InvalidOrderTransitionException;
use App\Jobs\SendOutboundRewardVoucherJob;
use App\Models\Order;
use App\Models\OutboundReward;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutboundRewardRepositoryInterface;
use App\Repositories\Contracts\PartnerStoreRepositoryInterface;
use App\Repositories\Contracts\ProductVariantRepositoryInterface;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OrderLifecycleService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected OrderRepositoryInterface $orderRepository,
        protected ProductVariantRepositoryInterface $variantRepository,
        protected CouponRepositoryInterface $couponRepository,
        protected CommissionRepositoryInterface $commissionRepository,
        protected OutboundRewardRepositoryInterface $outboundRewardRepository,
        protected PartnerStoreRepositoryInterface $partnerStoreRepository,
        protected TenantConfigRepositoryInterface $tenantConfigRepository
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateOrders(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->orderRepository->paginateForAdmin($filters, $perPage);
    }

    public function getOrderDetail(string $orderId): Order
    {
        $order = $this->orderRepository->findDetailedById($orderId);

        if (! $order) {
            throw new ModelNotFoundException("Orden con ID [{$orderId}] no encontrada.");
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function orchestrateStatusTransition(string $orderId, string $targetStatus, ?string $notes = null): array
    {
        return DB::transaction(function () use ($orderId, $targetStatus, $notes): array {
            $order = $this->lockAndResolveOrder($orderId);

            $this->validateStatusTransition($order, $targetStatus);

            return match ($targetStatus) {
                'COMPLETADA' => $this->executeCompletionFlow($order, $notes),
                'CANCELADA' => $this->executeCancellationFlow($order, $notes),
                default => throw new InvalidOrderTransitionException("Estado [{$targetStatus}] no admitido."),
            };
        });
    }

    public function lockAndResolveOrder(string $orderId): Order
    {
        $order = $this->orderRepository->findByIdWithLock($orderId);

        if (! $order) {
            throw new ModelNotFoundException("Orden con ID [{$orderId}] no encontrada.");
        }

        return $order->load(['items', 'commission', 'appliedCoupon']);
    }

    public function validateStatusTransition(Order $order, string $targetStatus): void
    {
        if (! $this->canTransition($order->status, $targetStatus)) {
            throw new InvalidOrderTransitionException(
                "No es posible cambiar el estado de una orden que ya se encuentra en estado {$order->status}."
            );
        }
    }

    public function canTransition(string $currentStatus, string $targetStatus): bool
    {
        if ($currentStatus !== 'PENDIENTE_PAGO') {
            return false;
        }

        return in_array($targetStatus, ['COMPLETADA', 'CANCELADA'], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function executeCompletionFlow(Order $order, ?string $notes): array
    {
        $order = $this->markOrderAsCompleted($order);

        $commissionStatus = $this->approveRetainedCommission($order);

        $reward = $this->evaluateAndIssueOutboundReward($order);

        if ($reward !== null) {
            $this->dispatchVoucherEmailAfterCommit($order, $reward);
        }

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => 'COMPLETADA',
            'commission_status' => $commissionStatus,
            'outbound_reward_generated' => $reward !== null,
            'outbound_reward_code' => $reward?->code,
            'completed_at' => $order->completed_at?->toISOString() ?? now()->toISOString(),
            'cancelled_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function executeCancellationFlow(Order $order, ?string $notes): array
    {
        $order = $this->markOrderAsCancelled($order);

        $this->restoreVariantsInventory($order);

        $this->releaseCouponUsageIfApplicable($order);

        $commissionStatus = $this->voidRetainedCommission($order);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => 'CANCELADA',
            'commission_status' => $commissionStatus,
            'outbound_reward_generated' => false,
            'outbound_reward_code' => null,
            'completed_at' => null,
            'cancelled_at' => $order->cancelled_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    protected function markOrderAsCompleted(Order $order): Order
    {
        $this->orderRepository->updateStatus($order, [
            'status' => 'COMPLETADA',
            'completed_at' => now(),
        ]);

        return $order->fresh(['items', 'commission', 'appliedCoupon']);
    }

    protected function markOrderAsCancelled(Order $order): Order
    {
        $this->orderRepository->updateStatus($order, [
            'status' => 'CANCELADA',
            'cancelled_at' => now(),
        ]);

        return $order->fresh(['items', 'commission', 'appliedCoupon']);
    }

    protected function approveRetainedCommission(Order $order): ?string
    {
        $commission = $order->commission ?? $this->commissionRepository->findByOrderId($order->id);

        if (! $commission) {
            return null;
        }

        if ($commission->status === 'RETENIDA') {
            $this->commissionRepository->updateStatus($commission, 'APROBADA');

            return 'APROBADA';
        }

        return $commission->status;
    }

    protected function voidRetainedCommission(Order $order): ?string
    {
        $commission = $order->commission ?? $this->commissionRepository->findByOrderId($order->id);

        if (! $commission) {
            return null;
        }

        if ($commission->status === 'RETENIDA') {
            $this->commissionRepository->updateStatus($commission, 'ANULADA');

            return 'ANULADA';
        }

        return $commission->status;
    }

    protected function restoreVariantsInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->variant_id) {
                continue;
            }

            $variant = $this->variantRepository->findByIdWithLockIncludingTrashed($item->variant_id);
            if ($variant) {
                $this->variantRepository->incrementStock($variant, (int) $item->quantity);
            }
        }
    }

    protected function releaseCouponUsageIfApplicable(Order $order): void
    {
        if (! $order->applied_coupon_id) {
            return;
        }

        $coupon = $this->couponRepository->findByIdWithLock($order->applied_coupon_id);
        if ($coupon) {
            $this->couponRepository->decrementUsage($coupon);
        }
    }

    public function evaluateAndIssueOutboundReward(Order $order): ?OutboundReward
    {
        $tenantId = $this->tenantContext->id();
        $config = $this->tenantConfigRepository->findByTenantId($tenantId);
        $rules = $config ? $config->outbound_reward_rules : [];

        $totalItems = (int) $order->items->sum('quantity');
        $totalNet = (float) $order->total_net;

        if (! $this->isOrderEligibleForOutboundReward($rules, $totalNet, $totalItems)) {
            return null;
        }

        $partnerStoreId = $rules['partner_store_id'] ?? $rules['reward_partner_id'] ?? null;
        if (! $partnerStoreId) {
            $firstPartner = $this->partnerStoreRepository->findFirstActiveForTenant();
            $partnerStoreId = $firstPartner?->id;
        }

        if (! $partnerStoreId) {
            return null;
        }

        $partnerStore = $this->partnerStoreRepository->findActiveById((string) $partnerStoreId);
        if (! $partnerStore || ! $partnerStore->is_active) {
            return null;
        }

        if ($this->outboundRewardRepository->existsForOrderAndPartner($order->id, $partnerStore->id)) {
            return null;
        }

        $expiresAt = $this->calculateRewardExpiration($rules);

        do {
            $code = $this->generateCryptographicVoucherCode();
        } while ($this->outboundRewardRepository->existsByCode($code));

        return $this->outboundRewardRepository->create([
            'order_id' => $order->id,
            'partner_store_id' => $partnerStore->id,
            'code' => $code,
            'status' => 'EMITIDO',
            'expires_at' => $expiresAt,
        ]);
    }

    protected function dispatchVoucherEmailAfterCommit(Order $order, OutboundReward $reward): void
    {
        $tenantId = $this->tenantContext->id();

        DB::afterCommit(function () use ($reward, $tenantId) {
            SendOutboundRewardVoucherJob::dispatch($reward->id, $tenantId);
        });
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function isOrderEligibleForOutboundReward(array $rules, float $orderTotalNet, int $totalItemsCount): bool
    {
        if (empty($rules) || empty($rules['is_enabled'])) {
            return false;
        }

        $minAmount = null;
        if (isset($rules['min_order_amount'])) {
            $minAmount = (float) $rules['min_order_amount'];
        } elseif (isset($rules['min_order_total'])) {
            $minAmount = (float) $rules['min_order_total'];
        }

        if ($minAmount !== null && $orderTotalNet < $minAmount) {
            return false;
        }

        if (isset($rules['min_items'])) {
            $minItems = (int) $rules['min_items'];
            if ($totalItemsCount < $minItems) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function calculateRewardExpiration(array $rules, ?CarbonInterface $referenceTime = null): CarbonInterface
    {
        $base = $referenceTime ? $referenceTime->copy() : now();
        $days = (int) ($rules['validity_days'] ?? $rules['expiration_days'] ?? 30);

        return $base->addDays($days);
    }

    public function generateCryptographicVoucherCode(): string
    {
        $bytes = random_bytes(6);

        return sprintf(
            'RWD-%s-%s-%s',
            strtoupper(substr(bin2hex(substr($bytes, 0, 2)), 0, 4)),
            strtoupper(substr(bin2hex(substr($bytes, 2, 2)), 0, 4)),
            strtoupper(substr(bin2hex(substr($bytes, 4, 2)), 0, 4))
        );
    }
}
