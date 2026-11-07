<?php

namespace App\Services\Cart;

use App\Context\TenantContext;
use App\Models\Affiliate;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Repositories\Contracts\AffiliateRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\ProductVariantRepositoryInterface;
use App\Repositories\Contracts\PromotionRepositoryInterface;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartPricingService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected ProductVariantRepositoryInterface $variantRepository,
        protected PromotionRepositoryInterface $promotionRepository,
        protected CouponRepositoryInterface $couponRepository,
        protected AffiliateRepositoryInterface $affiliateRepository,
        protected TenantConfigRepositoryInterface $tenantConfigRepository
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function calculateCartQuote(Tenant $tenant, array $payload): array
    {
        $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $variantsById = $this->resolveAndValidateVariants($rawItems);

        return $this->calculateCartQuoteForVariants($tenant, $variantsById, $payload);
    }

    /**
     * @param  Collection<string, ProductVariant>  $variantsById
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>|null  $preloadedShippingZones
     * @return array<string, mixed>
     */
    public function calculateCartQuoteForVariants(
        Tenant $tenant,
        Collection $variantsById,
        array $payload,
        ?array $preloadedShippingZones = null,
        ?Coupon $preloadedCoupon = null
    ): array {
        $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $lineTotalsResult = $this->calculateItemLineTotals($variantsById, $rawItems);
        $subtotal = $lineTotalsResult['subtotal'];

        $cartMetrics = [
            'subtotal' => $subtotal,
            'total_quantity' => $lineTotalsResult['total_quantity'],
            'category_ids_lookup' => $lineTotalsResult['category_ids_lookup'],
            'variant_ids_lookup' => $lineTotalsResult['variant_ids_lookup'],
        ];

        $autoPromo = $this->evaluateAutomaticPromotions($cartMetrics);
        $couponPromo = $preloadedCoupon !== null
            ? $this->evaluateCouponInstanceDiscount($preloadedCoupon, $cartMetrics)
            : $this->evaluateCouponDiscount(isset($payload['coupon_code']) ? (string) $payload['coupon_code'] : null, $cartMetrics);

        $resolvedPromotions = $this->resolveStackingAndDominantDiscount($autoPromo, $couponPromo, $subtotal);
        $effectiveAuto = $resolvedPromotions['auto'];
        $effectiveCoupon = $resolvedPromotions['coupon'];

        $hasFreeShipping = (bool) (($effectiveAuto['grants_free_shipping'] ?? false) || ($effectiveCoupon['grants_free_shipping'] ?? false));

        if ($preloadedShippingZones !== null) {
            $shippingZones = $preloadedShippingZones;
        } else {
            $tenantConfig = $this->tenantConfigRepository->findByTenantId($tenant->id);
            $shippingZones = $tenantConfig ? $tenantConfig->shipping_zones : [];
        }

        $zoneCode = $payload['shipping_zone_code'] ?? ($payload['shipping_address']['region_code'] ?? null);
        $shippingResult = $this->resolveShippingFee(isset($zoneCode) ? (string) $zoneCode : null, $hasFreeShipping, $shippingZones);

        return $this->consolidateCartSummary(
            $lineTotalsResult['is_valid'],
            $lineTotalsResult['items'],
            $subtotal,
            $effectiveAuto,
            $effectiveCoupon,
            $shippingResult
        );
    }

    /**
     * @param  array<int, mixed>  $items
     * @return Collection<string, ProductVariant>
     */
    public function resolveAndValidateVariants(array $items): Collection
    {
        $variantIds = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['variant_id']) && is_string($item['variant_id'])) {
                $variantIds[] = $item['variant_id'];
            }
        }
        $variantIds = array_values(array_unique($variantIds));
        $variants = $this->variantRepository->findManyActiveWithProduct($variantIds)->keyBy('id');

        foreach ($variantIds as $variantId) {
            if (! $variants->has($variantId)) {
                throw ValidationException::withMessages([
                    'items' => ["La variante seleccionada con ID [{$variantId}] no existe o no se encuentra disponible."],
                ]);
            }
        }

        return $variants;
    }

    /**
     * @param  Collection<string, ProductVariant>  $variantsById
     * @param  array<int, mixed>  $items
     * @return array{items: list<array<string, mixed>>, subtotal: float, total_quantity: int, is_valid: bool, category_ids_lookup: array<string, bool>, variant_ids_lookup: array<string, bool>}
     */
    public function calculateItemLineTotals(Collection $variantsById, array $items): array
    {
        $calculatedItems = [];
        $subtotal = 0.00;
        $totalQuantity = 0;
        $isValid = true;
        $categoryIdsLookup = [];
        $variantIdsLookup = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['variant_id'])) {
                continue;
            }

            $variant = $variantsById->get((string) $item['variant_id']);
            if (! $variant) {
                continue;
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $unitPrice = (float) ($variant->price !== null ? $variant->price : $variant->product->base_price);
            $lineTotal = round($unitPrice * $quantity, 2);
            $availableStock = (int) $variant->stock;
            $hasSufficientStock = $availableStock >= $quantity;

            if (! $hasSufficientStock) {
                $isValid = false;
            }

            $calculatedItems[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'sku' => $variant->sku,
                'variant_attributes' => $variant->metadata ?? [],
                'quantity' => $quantity,
                'available_stock' => $availableStock,
                'has_sufficient_stock' => $hasSufficientStock,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
            $totalQuantity += $quantity;

            if ($variant->product->category_id) {
                $categoryIdsLookup[(string) $variant->product->category_id] = true;
            }
            $variantIdsLookup[(string) $variant->id] = true;
        }

        return [
            'items' => $calculatedItems,
            'subtotal' => round($subtotal, 2),
            'total_quantity' => $totalQuantity,
            'is_valid' => $isValid,
            'category_ids_lookup' => $categoryIdsLookup,
            'variant_ids_lookup' => $variantIdsLookup,
        ];
    }

    /**
     * @param  array{subtotal: float, total_quantity: int, category_ids_lookup: array<string, bool>, variant_ids_lookup: array<string, bool>}  $cartMetrics
     * @return array<string, mixed>
     */
    public function evaluateAutomaticPromotions(array $cartMetrics): array
    {
        $automaticPromotions = $this->promotionRepository->getActiveAutomaticForTenant();
        $bestPromotion = null;
        $maxDiscount = 0.00;

        foreach ($automaticPromotions as $promotion) {
            if (! $this->matchesPromotionConditions($promotion, $cartMetrics)) {
                continue;
            }

            $discountAmount = $this->computeDiscountAmount($promotion, $cartMetrics['subtotal']);

            if ($bestPromotion === null || $discountAmount > $maxDiscount) {
                $bestPromotion = $promotion;
                $maxDiscount = $discountAmount;
            }
        }

        if (! $bestPromotion) {
            return [
                'applied' => false,
                'id' => null,
                'name' => null,
                'discount_amount' => 0.00,
                'allow_stacking' => true,
                'grants_free_shipping' => false,
            ];
        }

        return [
            'applied' => true,
            'id' => $bestPromotion->id,
            'name' => $bestPromotion->name,
            'discount_amount' => $maxDiscount,
            'allow_stacking' => (bool) $bestPromotion->allow_stacking,
            'grants_free_shipping' => $bestPromotion->discount_type === 'FREE_SHIPPING',
        ];
    }

    /**
     * @param  array{subtotal: float, total_quantity: int, category_ids_lookup: array<string, bool>, variant_ids_lookup: array<string, bool>}  $cartMetrics
     * @return array<string, mixed>|null
     */
    public function evaluateCouponDiscount(?string $couponCode, array $cartMetrics): ?array
    {
        if ($couponCode === null || trim($couponCode) === '') {
            return null;
        }

        $code = strtoupper(trim($couponCode));
        $coupon = $this->couponRepository->findByCodeWithPromotion($code);

        if (! $coupon) {
            return [
                'applied' => false,
                'id' => null,
                'code' => $code,
                'discount_amount' => 0.00,
                'rejection_code' => 'COUPON_NOT_FOUND',
                'message' => 'El cupón ingresado no existe.',
                'allow_stacking' => false,
                'grants_free_shipping' => false,
            ];
        }

        return $this->evaluateCouponInstanceDiscount($coupon, $cartMetrics);
    }

    /**
     * @param  array{subtotal: float, total_quantity: int, category_ids_lookup: array<string, bool>, variant_ids_lookup: array<string, bool>}  $cartMetrics
     * @return array<string, mixed>
     */
    public function evaluateCouponInstanceDiscount(Coupon $coupon, array $cartMetrics): array
    {
        if (! $coupon->is_active || ! $coupon->promotion || ! $coupon->promotion->is_active) {
            return [
                'applied' => false,
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_amount' => 0.00,
                'rejection_code' => 'COUPON_INACTIVE',
                'message' => 'El cupón no se encuentra activo.',
                'allow_stacking' => false,
                'grants_free_shipping' => false,
            ];
        }

        $now = now();
        $startsAt = $coupon->starts_at ?? $coupon->promotion->starts_at;
        $endsAt = $coupon->ends_at ?? $coupon->promotion->ends_at;

        if ($startsAt->isFuture() || ($endsAt !== null && $endsAt->isPast())) {
            return [
                'applied' => false,
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_amount' => 0.00,
                'rejection_code' => 'COUPON_EXPIRED',
                'message' => 'El cupón ingresado ha expirado o no se encuentra vigente.',
                'allow_stacking' => false,
                'grants_free_shipping' => false,
            ];
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return [
                'applied' => false,
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_amount' => 0.00,
                'rejection_code' => 'COUPON_EXHAUSTED',
                'message' => 'El cupón ha alcanzado el límite máximo de usos.',
                'allow_stacking' => false,
                'grants_free_shipping' => false,
            ];
        }

        if (! $this->matchesPromotionConditions($coupon->promotion, $cartMetrics)) {
            return [
                'applied' => false,
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_amount' => 0.00,
                'rejection_code' => 'CONDITIONS_NOT_MET',
                'message' => 'El carrito no cumple con las condiciones requeridas para este cupón.',
                'allow_stacking' => false,
                'grants_free_shipping' => false,
            ];
        }

        $discountAmount = $this->computeDiscountAmount($coupon->promotion, $cartMetrics['subtotal']);

        return [
            'applied' => true,
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount_amount' => $discountAmount,
            'allow_stacking' => (bool) $coupon->promotion->allow_stacking,
            'grants_free_shipping' => $coupon->promotion->discount_type === 'FREE_SHIPPING',
            'message' => "Cupón {$coupon->code} aplicado exitosamente.",
        ];
    }

    /**
     * @param  array<string, mixed>|null  $autoPromo
     * @param  array<string, mixed>|null  $couponPromo
     * @return array{auto: array<string, mixed>, coupon: array<string, mixed>}
     */
    public function resolveStackingAndDominantDiscount(?array $autoPromo, ?array $couponPromo, float $subtotal): array
    {
        $defaultAuto = [
            'applied' => false,
            'id' => null,
            'name' => null,
            'discount_amount' => 0.00,
            'allow_stacking' => true,
            'grants_free_shipping' => false,
        ];

        $defaultCoupon = [
            'applied' => false,
            'id' => null,
            'code' => null,
            'discount_amount' => 0.00,
            'message' => null,
            'allow_stacking' => true,
            'grants_free_shipping' => false,
        ];

        $auto = $autoPromo ?? $defaultAuto;
        $coupon = $couponPromo ?? $defaultCoupon;

        if (! ($auto['applied'] ?? false) || ! ($coupon['applied'] ?? false)) {
            return ['auto' => $auto, 'coupon' => $coupon];
        }

        $canStack = ($auto['allow_stacking'] ?? false) && ($coupon['allow_stacking'] ?? false);

        if ($canStack) {
            return ['auto' => $auto, 'coupon' => $coupon];
        }

        $autoDiscount = (float) ($auto['discount_amount'] ?? 0.00);
        $couponDiscount = (float) ($coupon['discount_amount'] ?? 0.00);

        if ($couponDiscount >= $autoDiscount) {
            $auto['applied'] = false;
            $auto['discount_amount'] = 0.00;
            $auto['grants_free_shipping'] = false;

            return ['auto' => $auto, 'coupon' => $coupon];
        }

        $coupon['applied'] = false;
        $coupon['discount_amount'] = 0.00;
        $coupon['grants_free_shipping'] = false;
        $coupon['rejection_code'] = 'NON_STACKABLE_LOWER_BENEFIT';
        $coupon['message'] = "La promoción automática {$auto['name']} otorga un mayor descuento que el cupón ingresado y no son acumulables.";

        return ['auto' => $auto, 'coupon' => $coupon];
    }

    /**
     * @param  list<array<string, mixed>>|null  $shippingZones
     * @return array{zone_code: ?string, zone_name: ?string, shipping_fee: float, is_free: bool}
     */
    public function resolveShippingFee(?string $zoneCode, bool $hasFreeShipping, ?array $shippingZones): array
    {
        if ($zoneCode === null || trim($zoneCode) === '') {
            return [
                'zone_code' => null,
                'zone_name' => null,
                'shipping_fee' => 0.00,
                'is_free' => false,
            ];
        }

        $normalizedZoneCode = strtoupper(trim($zoneCode));
        $indexedZones = [];

        foreach ($shippingZones ?? [] as $zone) {
            if (isset($zone['code'])) {
                $indexedZones[strtoupper((string) $zone['code'])] = $zone;
            }
        }

        $matchedZone = $indexedZones[$normalizedZoneCode] ?? null;

        if (! $matchedZone) {
            return [
                'zone_code' => $normalizedZoneCode,
                'zone_name' => null,
                'shipping_fee' => 0.00,
                'is_free' => $hasFreeShipping,
            ];
        }

        if ($hasFreeShipping) {
            return [
                'zone_code' => strtoupper((string) $matchedZone['code']),
                'zone_name' => $matchedZone['name'] ?? null,
                'shipping_fee' => 0.00,
                'is_free' => true,
            ];
        }

        return [
            'zone_code' => strtoupper((string) $matchedZone['code']),
            'zone_name' => $matchedZone['name'] ?? null,
            'shipping_fee' => round((float) ($matchedZone['fee'] ?? 0.00), 2),
            'is_free' => false,
        ];
    }

    /**
     * @param  array{subtotal: float, total_quantity: int, category_ids_lookup: array<string, bool>, variant_ids_lookup: array<string, bool>}  $cartMetrics
     */
    public function matchesPromotionConditions(Promotion $promotion, array $cartMetrics): bool
    {
        $conditions = $promotion->conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        if (isset($conditions['min_subtotal']) && $cartMetrics['subtotal'] < (float) $conditions['min_subtotal']) {
            return false;
        }

        if (isset($conditions['min_quantity']) && $cartMetrics['total_quantity'] < (int) $conditions['min_quantity']) {
            return false;
        }

        if (! empty($conditions['eligible_category_ids'])) {
            $matchedCategory = false;
            foreach ($conditions['eligible_category_ids'] as $categoryId) {
                if (isset($cartMetrics['category_ids_lookup'][$categoryId])) {
                    $matchedCategory = true;
                    break;
                }
            }
            if (! $matchedCategory) {
                return false;
            }
        }

        if (! empty($conditions['eligible_variant_ids'])) {
            $matchedVariant = false;
            foreach ($conditions['eligible_variant_ids'] as $variantId) {
                if (isset($cartMetrics['variant_ids_lookup'][$variantId])) {
                    $matchedVariant = true;
                    break;
                }
            }
            if (! $matchedVariant) {
                return false;
            }
        }

        return true;
    }

    public function computeDiscountAmount(Promotion $promotion, float $baseAmount): float
    {
        return match ($promotion->discount_type) {
            'PERCENTAGE' => min($baseAmount, round($baseAmount * ((float) $promotion->discount_value / 100), 2)),
            'FIXED_AMOUNT' => min($baseAmount, round((float) $promotion->discount_value, 2)),
            'FREE_SHIPPING' => 0.00,
            default => 0.00,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $coupon
     * @param  array<string, mixed>  $shipping
     * @return array<string, mixed>
     */
    protected function consolidateCartSummary(
        bool $isValid,
        array $items,
        float $subtotal,
        array $auto,
        array $coupon,
        array $shipping
    ): array {
        $autoDiscount = (float) ($auto['discount_amount'] ?? 0.00);
        $couponDiscount = (float) ($coupon['discount_amount'] ?? 0.00);

        $totalDiscount = min($subtotal, $autoDiscount + $couponDiscount);
        $discountedSubtotal = max(0.00, $subtotal - $totalDiscount);
        $shippingFee = (float) ($shipping['shipping_fee'] ?? 0.00);
        $totalNet = round($discountedSubtotal + $shippingFee, 2);

        $autoResponse = [
            'applied' => (bool) ($auto['applied'] ?? false),
            'id' => $auto['id'] ?? null,
            'name' => $auto['name'] ?? null,
            'discount_amount' => $autoDiscount,
        ];

        $couponResponse = [
            'applied' => (bool) ($coupon['applied'] ?? false),
            'id' => $coupon['id'] ?? null,
            'code' => $coupon['code'] ?? null,
            'discount_amount' => $couponDiscount,
        ];

        if (isset($coupon['rejection_code'])) {
            $couponResponse['rejection_code'] = $coupon['rejection_code'];
        }
        if (isset($coupon['message'])) {
            $couponResponse['message'] = $coupon['message'];
        }

        return [
            'is_valid' => $isValid,
            'items' => $items,
            'summary' => [
                'subtotal' => $subtotal,
                'auto_promotion' => $autoResponse,
                'coupon' => $couponResponse,
                'shipping' => [
                    'zone_code' => $shipping['zone_code'] ?? null,
                    'zone_name' => $shipping['zone_name'] ?? null,
                    'shipping_fee' => $shippingFee,
                    'is_free' => (bool) ($shipping['is_free'] ?? false),
                ],
                'total_net' => $totalNet,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orchestratePromotionCreation(Tenant $tenant, array $payload): Promotion
    {
        return $this->promotionRepository->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orchestratePromotionUpdate(Tenant $tenant, string $id, array $payload): Promotion
    {
        $promotion = $this->promotionRepository->findById($id);

        if (! $promotion) {
            throw ValidationException::withMessages([
                'id' => ['Promoción no encontrada.'],
            ]);
        }

        return $this->promotionRepository->update($promotion, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orchestrateCouponCreation(Tenant $tenant, array $payload): Coupon
    {
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));

        if ($this->couponRepository->existsByCode($code)) {
            throw ValidationException::withMessages([
                'code' => ['El código de cupón ya se encuentra registrado en este inquilino.'],
            ]);
        }

        $promotion = $this->promotionRepository->findById($payload['promotion_id']);
        if (! $promotion) {
            throw ValidationException::withMessages([
                'promotion_id' => ['La promoción asignada al cupón no existe.'],
            ]);
        }

        if (! empty($payload['affiliate_id'])) {
            $affiliate = $this->affiliateRepository->findById($payload['affiliate_id']);
            if (! $affiliate) {
                throw ValidationException::withMessages([
                    'affiliate_id' => ['El afiliado asignado al cupón no existe.'],
                ]);
            }
        }

        $payload['code'] = $code;

        return $this->couponRepository->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orchestrateAffiliateCreation(Tenant $tenant, array $payload): Affiliate
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        if ($this->affiliateRepository->existsByEmail($email)) {
            throw ValidationException::withMessages([
                'email' => ['El correo electrónico del afiliado ya está registrado.'],
            ]);
        }

        $payload['email'] = $email;

        return $this->affiliateRepository->create($payload);
    }
}
