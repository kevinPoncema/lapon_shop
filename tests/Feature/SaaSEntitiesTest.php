<?php

use App\Models\Affiliate;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfig;
use Illuminate\Database\QueryException;

test('tenant can be created and has relations', function () {
    $tenant = Tenant::create([
        'name' => 'Comercio Alpha',
        'slug' => 'comercio-alpha',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
        'is_active' => true,
    ]);

    expect($tenant->id)->toBeString()
        ->and($tenant->is_active)->toBeTrue();

    $config = TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => ['primary_color' => '#1E293B'],
        'contact_info' => ['phone' => '+50499887766'],
        'shipping_zones' => [['code' => 'HN-CR', 'fee' => 80.00]],
        'metadata_schema' => [
            [
                'key' => 'talla',
                'label' => 'Talla',
                'data_type' => 'select',
                'options' => ['S', 'M', 'L'],
                'is_required' => true,
                'affects_stock' => true,
            ],
        ],
        'filter_config' => [
            ['type' => 'system', 'key' => 'price', 'order' => 1],
        ],
        'outbound_reward_rules' => ['min_order_total' => 1000.00],
    ]);

    expect($tenant->config)->not->toBeNull()
        ->and($tenant->config->tenant_id)->toBe($tenant->id)
        ->and($config->branding)->toBe(['primary_color' => '#1E293B'])
        ->and($config->tenant->id)->toBe($tenant->id);
});

test('catalog hierarchy and soft deletes function correctly', function () {
    $tenant = Tenant::create([
        'name' => 'Store Beta',
        'slug' => 'store-beta',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $category = Category::create([
        'tenant_id' => $tenant->id,
        'name' => 'Ropa Masculina',
        'slug' => 'ropa-masculina',
    ]);

    $subcategory = Category::create([
        'tenant_id' => $tenant->id,
        'parent_id' => $category->id,
        'name' => 'Camisas',
        'slug' => 'camisas',
    ]);

    expect($subcategory->parent->id)->toBe($category->id)
        ->and($category->children)->toHaveCount(1);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'category_id' => $subcategory->id,
        'name' => 'Camisa Oxford',
        'slug' => 'camisa-oxford',
        'base_price' => 650.00,
        'metadata' => ['material' => 'Algodón Pima 100%'],
        'images' => ['https://cdn.example.com/p1.webp'],
    ]);

    $variant = ProductVariant::create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sku' => 'OXF-BLA-M',
        'price' => 650.00,
        'stock' => 10,
        'metadata' => ['talla' => 'M', 'color' => 'Blanco'],
        'is_default' => true,
    ]);

    expect($product->variants)->toHaveCount(1)
        ->and($variant->product->id)->toBe($product->id)
        ->and($variant->stock)->toBe(10)
        ->and($product->base_price)->toBe('650.00');

    // Test Soft Deletes
    $product->delete();
    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

test('variant stock check constraint prevents negative stock', function () {
    $tenant = Tenant::create([
        'name' => 'Store Gamma',
        'slug' => 'store-gamma',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'name' => 'Pantalón Chino',
        'slug' => 'pantalon-chino',
        'base_price' => 800.00,
    ]);

    expect(function () use ($tenant, $product) {
        ProductVariant::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'sku' => 'PAN-NEG-32',
            'stock' => -5,
        ]);
    })->toThrow(QueryException::class);
});

test('promotions, coupons, affiliates and commissions cycle', function () {
    $tenant = Tenant::create([
        'name' => 'Store Delta',
        'slug' => 'store-delta',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $promotion = Promotion::create([
        'tenant_id' => $tenant->id,
        'name' => 'Descuento 10% Influencers',
        'type' => 'COUPON',
        'discount_type' => 'PERCENTAGE',
        'discount_value' => 10.00,
        'allow_stacking' => false,
        'starts_at' => now(),
    ]);

    $affiliate = Affiliate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Juan Pérez',
        'email' => 'juan@influencer.hn',
        'phone' => '+50499001122',
        'commission_type' => 'PERCENTAGE',
        'commission_value' => 5.00,
        'payment_info' => ['bank' => 'BAC', 'account' => '123456789'],
    ]);

    $coupon = Coupon::create([
        'tenant_id' => $tenant->id,
        'promotion_id' => $promotion->id,
        'affiliate_id' => $affiliate->id,
        'code' => 'JUAN10',
        'max_uses' => 50,
        'used_count' => 0,
        'starts_at' => now(),
    ]);

    expect($coupon->promotion->id)->toBe($promotion->id)
        ->and($coupon->affiliate->id)->toBe($affiliate->id);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'name' => 'Zapato Formal',
        'slug' => 'zapato-formal',
        'base_price' => 1200.00,
    ]);

    $variant = ProductVariant::create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sku' => 'ZAP-41',
        'stock' => 15,
    ]);

    $order = Order::create([
        'tenant_id' => $tenant->id,
        'order_number' => '#ORD-1001',
        'status' => 'PENDIENTE_PAGO',
        'customer_info' => ['name' => 'Carlos Cliente', 'phone' => '+50498765432'],
        'shipping_address' => ['department' => 'Cortés', 'city' => 'San Pedro Sula'],
        'subtotal' => 1200.00,
        'auto_discount_total' => 0.00,
        'coupon_discount_total' => 120.00,
        'shipping_fee' => 80.00,
        'total_net' => 1160.00,
        'applied_coupon_id' => $coupon->id,
        'whatsapp_url' => 'https://wa.me/50499001122?text=Order1001',
    ]);

    $orderItem = OrderItem::create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 1200.00,
        'line_total' => 1200.00,
        'variant_snapshot' => ['sku' => 'ZAP-41', 'name' => 'Zapato Formal'],
    ]);

    $commission = Commission::create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'affiliate_id' => $affiliate->id,
        'coupon_id' => $coupon->id,
        'amount' => 58.00,
        'status' => 'RETENIDA',
    ]);

    expect($order->items)->toHaveCount(1)
        ->and($order->commission->id)->toBe($commission->id)
        ->and($orderItem->order->id)->toBe($order->id)
        ->and($commission->affiliate->id)->toBe($affiliate->id)
        ->and($commission->coupon->id)->toBe($coupon->id);

    // Partner stores and outbound rewards
    $partnerStore = PartnerStore::create([
        'tenant_id' => $tenant->id,
        'name' => 'Café Gourmet San Pedro',
        'portal_email' => 'gerencia@cafegourmet.hn',
        'portal_password' => 'secret123',
        'benefit_title' => '2x1 en Cappuccino',
        'terms_conditions' => 'Válido de lunes a viernes.',
        'contact_info' => ['address' => 'Barrio Guamilito'],
    ]);

    $outboundReward = OutboundReward::create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'partner_store_id' => $partnerStore->id,
        'code' => 'RWD-ALPHA-1234',
        'status' => 'EMITIDO',
        'expires_at' => now()->addDays(30),
    ]);

    expect($order->outboundRewards)->toHaveCount(1)
        ->and($outboundReward->partnerStore->id)->toBe($partnerStore->id)
        ->and($outboundReward->order->id)->toBe($order->id);
});
