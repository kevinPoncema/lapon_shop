<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Services\Cart\CartPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => fake()->unique()->slug(), 'is_active' => true]);
});

test('calculo matematico de line_total y subtotal con decimales exactos y sobrescritura de precio', function () {
    $service = app(CartPricingService::class);

    $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $category->id,
        'base_price' => 100.00,
    ]);

    $variantInherited = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'price' => null,
        'stock' => 10,
    ]);

    $variantOverridden = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'price' => 125.50,
        'stock' => 10,
    ]);

    $variantsById = collect([
        $variantInherited->id => $variantInherited->load('product'),
        $variantOverridden->id => $variantOverridden->load('product'),
    ]);

    $items = [
        ['variant_id' => $variantInherited->id, 'quantity' => 2],
        ['variant_id' => $variantOverridden->id, 'quantity' => 3],
    ];

    $result = $service->calculateItemLineTotals($variantsById, $items);

    expect($result['subtotal'])->toBe(576.50)
        ->and($result['total_quantity'])->toBe(5)
        ->and($result['items'][0]['line_total'])->toBe(200.00)
        ->and($result['items'][1]['line_total'])->toBe(376.50);
});

test('evaluador de condiciones jsonb valida correctamente umbrales de categoria y subtotal en O1', function () {
    $service = app(CartPricingService::class);

    $catA = '11111111-1111-1111-1111-111111111111';
    $catB = '22222222-2222-2222-2222-222222222222';

    $promotion = new Promotion([
        'conditions' => [
            'min_subtotal' => 500.00,
            'min_quantity' => 3,
            'eligible_category_ids' => [$catA],
        ],
    ]);

    $matchingMetrics = [
        'subtotal' => 600.00,
        'total_quantity' => 4,
        'category_ids_lookup' => [$catA => true],
        'variant_ids_lookup' => [],
    ];

    $nonMatchingSubtotalMetrics = [
        'subtotal' => 400.00,
        'total_quantity' => 4,
        'category_ids_lookup' => [$catA => true],
        'variant_ids_lookup' => [],
    ];

    $nonMatchingCategoryMetrics = [
        'subtotal' => 600.00,
        'total_quantity' => 4,
        'category_ids_lookup' => [$catB => true],
        'variant_ids_lookup' => [],
    ];

    expect($service->matchesPromotionConditions($promotion, $matchingMetrics))->toBeTrue()
        ->and($service->matchesPromotionConditions($promotion, $nonMatchingSubtotalMetrics))->toBeFalse()
        ->and($service->matchesPromotionConditions($promotion, $nonMatchingCategoryMetrics))->toBeFalse();
});

test('resolucion de flete maneja codigos de departamento en minusculas y mayusculas o free shipping', function () {
    $service = app(CartPricingService::class);

    $shippingZones = [
        ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 80.00],
        ['code' => 'HN-FM', 'name' => 'Francisco Morazán', 'fee' => 120.00],
    ];

    $feeStandard = $service->resolveShippingFee('hn-cr', false, $shippingZones);
    $feeFree = $service->resolveShippingFee('HN-FM', true, $shippingZones);
    $feeUnknown = $service->resolveShippingFee('HN-IB', false, $shippingZones);

    expect($feeStandard['shipping_fee'])->toBe(80.00)
        ->and($feeStandard['is_free'])->toBeFalse()
        ->and($feeFree['shipping_fee'])->toBe(0.00)
        ->and($feeFree['is_free'])->toBeTrue()
        ->and($feeUnknown['shipping_fee'])->toBe(0.00);
});

test('evaluador de apilamiento selecciona mayor beneficio cuando allow_stacking es false', function () {
    $service = app(CartPricingService::class);

    $autoPromo = [
        'applied' => true,
        'id' => 'promo-auto-1',
        'name' => 'Auto 10%',
        'discount_amount' => 100.00,
        'allow_stacking' => false,
        'grants_free_shipping' => false,
    ];

    $couponPromo = [
        'applied' => true,
        'id' => 'coupon-20',
        'code' => 'PROMO20',
        'discount_amount' => 200.00,
        'allow_stacking' => false,
        'grants_free_shipping' => false,
        'message' => 'Cupón 20% aplicado.',
    ];

    $resolved = $service->resolveStackingAndDominantDiscount($autoPromo, $couponPromo, 1000.00);

    expect($resolved['auto']['applied'])->toBeFalse()
        ->and($resolved['coupon']['applied'])->toBeTrue()
        ->and($resolved['coupon']['discount_amount'])->toBe(200.00);
});
