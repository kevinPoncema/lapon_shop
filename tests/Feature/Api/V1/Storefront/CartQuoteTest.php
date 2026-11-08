<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfig;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-carrito',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $this->tenant->id,
        'shipping_zones' => [
            ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 80.00],
            ['code' => 'HN-FM', 'name' => 'Francisco Morazán', 'fee' => 120.00],
        ],
    ]);

    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Zapato Oxford Clásico',
        'base_price' => 1450.00,
    ]);

    $this->variant1 = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'sku' => 'OXF-39-NEG',
        'price' => null,
        'stock' => 15,
        'metadata' => ['talla' => '39', 'color' => 'Negro'],
        'is_active' => true,
    ]);

    $this->variant2 = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'sku' => 'OXF-41-CAFE',
        'price' => 1550.00,
        'stock' => 8,
        'metadata' => ['talla' => '41', 'color' => 'Café'],
        'is_active' => true,
    ]);
});

test('calcula correctamente el subtotal e importes para multiples variantes sin promociones', function () {
    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 2],
            ['variant_id' => $this->variant2->id, 'quantity' => 1],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('is_valid', true)
        ->assertJsonPath('items.0.sku', 'OXF-39-NEG')
        ->assertJsonPath('items.0.unit_price', 1450)
        ->assertJsonPath('items.0.line_total', 2900)
        ->assertJsonPath('items.1.sku', 'OXF-41-CAFE')
        ->assertJsonPath('items.1.unit_price', 1550)
        ->assertJsonPath('items.1.line_total', 1550)
        ->assertJsonPath('summary.subtotal', 4450)
        ->assertJsonPath('summary.auto_promotion.applied', false)
        ->assertJsonPath('summary.coupon.applied', false)
        ->assertJsonPath('summary.total_net', 4450);
});

test('aplica promocion automatica de descuento porcentual cuando se supera el subtotal minimo', function () {
    Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento 10% por compra mayor a 2000',
        'type' => 'AUTOMATIC',
        'discount_type' => 'PERCENTAGE',
        'discount_value' => 10.00,
        'conditions' => ['min_subtotal' => 2000.00],
        'allow_stacking' => false,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(10),
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 2],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.subtotal', 2900)
        ->assertJsonPath('summary.auto_promotion.applied', true)
        ->assertJsonPath('summary.auto_promotion.discount_amount', 290)
        ->assertJsonPath('summary.total_net', 2610);
});

test('aplica exitosamente cupon manual con descuento de monto fijo', function () {
    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Bono de Bienvenida',
        'type' => 'COUPON',
        'discount_type' => 'FIXED_AMOUNT',
        'discount_value' => 250.00,
        'allow_stacking' => false,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(10),
        'is_active' => true,
    ]);

    Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'code' => 'BIENVENIDA250',
        'max_uses' => 50,
        'used_count' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(10),
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'coupon_code' => 'bienvenida250',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.subtotal', 1450)
        ->assertJsonPath('summary.coupon.applied', true)
        ->assertJsonPath('summary.coupon.code', 'BIENVENIDA250')
        ->assertJsonPath('summary.coupon.discount_amount', 250)
        ->assertJsonPath('summary.total_net', 1200);
});

test('calcula tarifa de envio regional segun el departamento seleccionado', function () {
    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'shipping_zone_code' => 'hn-fm',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.shipping.zone_code', 'HN-FM')
        ->assertJsonPath('summary.shipping.shipping_fee', 120)
        ->assertJsonPath('summary.shipping.is_free', false)
        ->assertJsonPath('summary.total_net', 1570);
});

test('aplica envio gratis cuando la promocion activa es de tipo FREE_SHIPPING', function () {
    Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Envío Gratis Superando 2000',
        'type' => 'AUTOMATIC',
        'discount_type' => 'FREE_SHIPPING',
        'discount_value' => 0.00,
        'conditions' => ['min_subtotal' => 2000.00],
        'allow_stacking' => false,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(10),
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 2],
        ],
        'shipping_zone_code' => 'HN-CR',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.shipping.shipping_fee', 0)
        ->assertJsonPath('summary.shipping.is_free', true)
        ->assertJsonPath('summary.total_net', 2900);
});

test('informa rechazo sin romper la cotizacion cuando el cupon no existe', function () {
    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'coupon_code' => 'NOEXISTE',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.coupon.applied', false)
        ->assertJsonPath('summary.coupon.rejection_code', 'COUPON_NOT_FOUND')
        ->assertJsonPath('summary.total_net', 1450);
});

test('informa rechazo de cupon cuando ha superado su limite maximo de usos', function () {
    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'COUPON',
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'code' => 'AGOTADO',
        'max_uses' => 5,
        'used_count' => 5,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'coupon_code' => 'AGOTADO',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.coupon.applied', false)
        ->assertJsonPath('summary.coupon.rejection_code', 'COUPON_EXHAUSTED');
});

test('informa rechazo de cupon cuando su fecha de vigencia ha expirado', function () {
    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'COUPON',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
        'is_active' => true,
    ]);

    Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'code' => 'EXPIRADO',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'coupon_code' => 'EXPIRADO',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.coupon.applied', false)
        ->assertJsonPath('summary.coupon.rejection_code', 'COUPON_EXPIRED');
});

test('aplica regla de no apilamiento eligiendo el mayor beneficio cuando se combinan cupon y promo automatica', function () {
    Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Auto 10%',
        'type' => 'AUTOMATIC',
        'discount_type' => 'PERCENTAGE',
        'discount_value' => 10.00,
        'allow_stacking' => false,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $couponPromo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cupón 20%',
        'type' => 'COUPON',
        'discount_type' => 'PERCENTAGE',
        'discount_value' => 20.00,
        'allow_stacking' => false,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $couponPromo->id,
        'code' => 'MEJOR20',
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'coupon_code' => 'MEJOR20',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.auto_promotion.applied', false)
        ->assertJsonPath('summary.coupon.applied', true)
        ->assertJsonPath('summary.coupon.discount_amount', 290)
        ->assertJsonPath('summary.total_net', 1160);
});

test('impide que un descuento de monto fijo superior al subtotal genere un total neto negativo', function () {
    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'COUPON',
        'discount_type' => 'FIXED_AMOUNT',
        'discount_value' => 5000.00,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'code' => 'SUPERDESCUENTO',
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'coupon_code' => 'SUPERDESCUENTO',
        'shipping_zone_code' => 'HN-CR',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('summary.subtotal', 1450)
        ->assertJsonPath('summary.coupon.discount_amount', 1450)
        ->assertJsonPath('summary.shipping.shipping_fee', 80)
        ->assertJsonPath('summary.total_net', 80);
});

test('rechaza con 422 peticiones de cotizacion con items vacios o cantidades menores a 1', function () {
    $responseEmpty = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', ['items' => []]);

    $responseEmpty->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    $responseInvalidQty = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', [
            'items' => [['variant_id' => $this->variant1->id, 'quantity' => 0]],
        ]);

    $responseInvalidQty->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity']);
});

test('marca stock insuficiente en los items cuando la cantidad solicitada supera el stock de la variante', function () {
    $payload = [
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 20],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-carrito')
        ->postJson('/api/v1/storefront/cart/quote', $payload);

    $response->assertOk()
        ->assertJsonPath('is_valid', false)
        ->assertJsonPath('items.0.available_stock', 15)
        ->assertJsonPath('items.0.has_sufficient_stock', false);
});
