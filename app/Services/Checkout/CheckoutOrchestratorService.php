<?php

namespace App\Services\Checkout;

use App\Context\TenantContext;
use App\Exceptions\Domain\CheckoutTransactionInProgressException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\OrderItemRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductVariantRepositoryInterface;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use App\Services\Cart\CartPricingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutOrchestratorService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected ProductVariantRepositoryInterface $variantRepository,
        protected CouponRepositoryInterface $couponRepository,
        protected OrderRepositoryInterface $orderRepository,
        protected OrderItemRepositoryInterface $orderItemRepository,
        protected CommissionRepositoryInterface $commissionRepository,
        protected TenantConfigRepositoryInterface $tenantConfigRepository,
        protected CartPricingService $cartPricingService,
        protected WhatsAppOrderMessageBuilder $whatsAppBuilder
    ) {}

    /**
     * @param  array<string, mixed>  $checkoutData
     * @return array{payload: array<string, mixed>, is_replay: bool}
     */
    public function orchestrateCheckout(Tenant $tenant, array $checkoutData, string $idempotencyKey): array
    {
        $existing = $this->checkIdempotency($tenant, $idempotencyKey);
        if ($existing !== null) {
            return ['payload' => $existing, 'is_replay' => true];
        }

        $lockKey = "idempotency_lock:{$tenant->id}:{$idempotencyKey}";
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            throw new CheckoutTransactionInProgressException;
        }

        try {
            $resultPayload = DB::transaction(fn () => $this->executeCheckoutTransaction($tenant, $checkoutData));

            $this->storeIdempotencyResponse($tenant, $idempotencyKey, $resultPayload);

            return ['payload' => $resultPayload, 'is_replay' => false];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $checkoutData
     * @return array<string, mixed>
     */
    protected function executeCheckoutTransaction(Tenant $tenant, array $checkoutData): array
    {
        $rawItems = is_array($checkoutData['items'] ?? null) ? $checkoutData['items'] : [];
        $quantitiesByVariant = $this->aggregateRequestedQuantities($rawItems);

        $lockedVariants = $this->reserveAndLockInventory($quantitiesByVariant);

        $couponCode = isset($checkoutData['coupon_code']) ? (string) $checkoutData['coupon_code'] : null;
        $lockedCoupon = $this->lockAndResolveCoupon($couponCode);

        $config = $this->tenantConfigRepository->findByTenantId($tenant->id);
        $shippingZones = $config ? $config->shipping_zones : [];

        $pricingResult = $this->cartPricingService->calculateCartQuoteForVariants(
            $tenant,
            $lockedVariants,
            $checkoutData,
            $shippingZones,
            $lockedCoupon
        );

        $this->deductInventory($lockedVariants, $quantitiesByVariant);

        if (! empty($pricingResult['summary']['coupon']['applied']) && $lockedCoupon !== null) {
            $this->couponRepository->incrementUsage($lockedCoupon);
        }

        $orderNumber = $this->generateUniqueOrderNumber();

        $pricingForWa = [
            'subtotal' => $pricingResult['summary']['subtotal'],
            'auto_discount_total' => $pricingResult['summary']['auto_promotion']['discount_amount'] ?? 0.00,
            'coupon_discount_total' => $pricingResult['summary']['coupon']['discount_amount'] ?? 0.00,
            'coupon_code' => $lockedCoupon?->code,
            'shipping_fee' => $pricingResult['summary']['shipping']['shipping_fee'] ?? 0.00,
            'shipping_zone_name' => $pricingResult['summary']['shipping']['zone_name'] ?? null,
            'total_net' => $pricingResult['summary']['total_net'],
            'items' => $pricingResult['items'],
        ];

        /** @var array<string, mixed> $customerInfo */
        $customerInfo = (array) ($checkoutData['customer_info'] ?? []);
        /** @var array<string, mixed> $shippingAddress */
        $shippingAddress = (array) ($checkoutData['shipping_address'] ?? []);

        $whatsAppUrl = $this->whatsAppBuilder->buildRedirectUrl(
            $tenant,
            $orderNumber,
            $customerInfo,
            $shippingAddress,
            $pricingForWa,
            $config
        );

        $order = $this->orderRepository->create([
            'order_number' => $orderNumber,
            'status' => 'PENDIENTE_PAGO',
            'customer_info' => $customerInfo,
            'shipping_address' => $shippingAddress,
            'subtotal' => $pricingResult['summary']['subtotal'],
            'auto_discount_total' => $pricingResult['summary']['auto_promotion']['discount_amount'] ?? 0.00,
            'coupon_discount_total' => $pricingResult['summary']['coupon']['discount_amount'] ?? 0.00,
            'shipping_fee' => $pricingResult['summary']['shipping']['shipping_fee'] ?? 0.00,
            'total_net' => $pricingResult['summary']['total_net'],
            'applied_promotion_id' => $pricingResult['summary']['auto_promotion']['id'] ?? null,
            'applied_coupon_id' => ! empty($pricingResult['summary']['coupon']['applied']) ? $lockedCoupon?->id : null,
            'whatsapp_url' => $whatsAppUrl,
        ]);

        $orderItemsData = [];
        foreach ($pricingResult['items'] as $item) {
            $variant = $lockedVariants->get((string) $item['variant_id']);
            if ($variant) {
                $orderItemsData[] = [
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'line_total' => (float) $item['line_total'],
                    'variant_snapshot' => [
                        'sku' => $variant->sku,
                        'product_name' => $variant->product->name,
                        'attributes' => $variant->metadata ?? [],
                        'unit_price' => (float) $item['unit_price'],
                    ],
                ];
            }
        }

        $this->orderItemRepository->createManyForOrder($order, $orderItemsData);

        if (! empty($pricingResult['summary']['coupon']['applied']) && $lockedCoupon !== null) {
            $this->recordAffiliateCommission($order, $lockedCoupon, (float) $order->subtotal);
        }

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer' => [
                'full_name' => $order->customer_info['full_name'] ?? '',
                'whatsapp_phone' => $order->customer_info['whatsapp_phone'] ?? '',
            ],
            'summary' => [
                'subtotal' => (float) $order->subtotal,
                'auto_discount_total' => (float) $order->auto_discount_total,
                'coupon_discount_total' => (float) $order->coupon_discount_total,
                'shipping_fee' => (float) $order->shipping_fee,
                'total_net' => (float) $order->total_net,
            ],
            'whatsapp_redirect_url' => $order->whatsapp_url,
            'created_at' => $order->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, int>
     */
    public function aggregateRequestedQuantities(array $items): array
    {
        $aggregated = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['variant_id'])) {
                $varId = (string) $item['variant_id'];
                $qty = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                $aggregated[$varId] = ($aggregated[$varId] ?? 0) + $qty;
            }
        }

        return $aggregated;
    }

    /**
     * @param  array<string, int>  $quantitiesByVariant
     * @return Collection<string, ProductVariant>
     */
    public function reserveAndLockInventory(array $quantitiesByVariant): Collection
    {
        $variantIds = array_keys($quantitiesByVariant);
        /** @var Collection<string, ProductVariant> $variants */
        $variants = $this->variantRepository->findManyWithLock($variantIds)->keyBy('id');

        foreach ($quantitiesByVariant as $variantId => $requestedQty) {
            $variant = $variants->get($variantId);

            if (! $variant || ! $variant->is_active || ! $variant->product || ! $variant->product->is_active) {
                throw new InsufficientStockException("La variante [{$variantId}] no se encuentra disponible.");
            }

            if ($variant->stock < $requestedQty) {
                throw new InsufficientStockException("El stock para la variante [{$variant->sku}] se ha agotado durante el proceso de compra. Stock disponible: {$variant->stock}, solicitado: {$requestedQty}.");
            }
        }

        return $variants;
    }

    public function lockAndResolveCoupon(?string $couponCode): ?Coupon
    {
        if ($couponCode === null || trim($couponCode) === '') {
            return null;
        }

        return $this->couponRepository->findByCodeWithLock(strtoupper(trim($couponCode)));
    }

    /**
     * @param  Collection<string, ProductVariant>  $lockedVariants
     * @param  array<string, int>  $quantitiesByVariant
     */
    public function deductInventory(Collection $lockedVariants, array $quantitiesByVariant): void
    {
        foreach ($quantitiesByVariant as $variantId => $qty) {
            $variant = $lockedVariants->get($variantId);
            if ($variant) {
                $this->variantRepository->decrementStock($variant, $qty);
            }
        }
    }

    public function recordAffiliateCommission(Order $order, ?Coupon $coupon, float $subtotal): ?Commission
    {
        if (! $coupon || ! $coupon->affiliate_id || ! $coupon->affiliate || ! $coupon->affiliate->is_active) {
            return null;
        }

        $affiliate = $coupon->affiliate;
        $commissionValue = (float) $affiliate->commission_value;
        $commissionType = strtoupper(trim((string) $affiliate->commission_type));

        $amount = match ($commissionType) {
            'PERCENTAGE' => round($subtotal * ($commissionValue / 100), 2),
            default => round($commissionValue, 2),
        };

        return $this->commissionRepository->create([
            'order_id' => $order->id,
            'affiliate_id' => $affiliate->id,
            'coupon_id' => $coupon->id,
            'amount' => $amount,
            'status' => 'RETENIDA',
        ]);
    }

    public function generateUniqueOrderNumber(): string
    {
        do {
            $candidate = '#ORD-'.strtoupper(Str::random(6));
        } while ($this->orderRepository->existsByOrderNumber($candidate));

        return $candidate;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function checkIdempotency(Tenant $tenant, string $idempotencyKey): ?array
    {
        $cacheKey = "idempotency:{$tenant->id}:{$idempotencyKey}";
        $cached = Cache::get($cacheKey);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeIdempotencyResponse(Tenant $tenant, string $idempotencyKey, array $payload): void
    {
        $cacheKey = "idempotency:{$tenant->id}:{$idempotencyKey}";
        Cache::put($cacheKey, $payload, 86400);
    }
}
