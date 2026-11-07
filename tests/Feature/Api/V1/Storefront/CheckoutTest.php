<?php

use App\Models\Affiliate;
use App\Models\Coupon;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-checkout',
        'is_active' => true,
    ]);

    $this->config = TenantConfig::factory()->create([
        'tenant_id' => $this->tenant->id,
        'contact_info' => [
            'phone' => '+504 9900-1122',
            'whatsapp_sales' => '+504 9988-7766',
            'bank_accounts' => [
                ['bank' => 'Banco Ficohsa', 'account' => '2000-1234-5678', 'type' => 'Cheques', 'holder' => 'Tienda Checkout S.A.'],
            ],
        ],
        'shipping_zones' => [
            ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 80.00],
            ['code' => 'HN-FM', 'name' => 'Francisco Morazán', 'fee' => 120.00],
        ],
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zapato Oxford Clásico',
        'base_price' => 1450.00,
        'is_active' => true,
    ]);

    $this->variant1 = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'sku' => 'OXF-39-NEG',
        'price' => null,
        'stock' => 10,
        'metadata' => ['talla' => '39', 'color' => 'Negro'],
        'is_active' => true,
    ]);

    $this->variant2 = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'sku' => 'OXF-41-CAFE',
        'price' => 1550.00,
        'stock' => 5,
        'metadata' => ['talla' => '41', 'color' => 'Café'],
        'is_active' => true,
    ]);
});

test('completa exitosamente el checkout creando orden items y descontando stock en base de datos', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo, 10 Calle, Casa #42',
            'reference' => 'Portón gris',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 2],
            ['variant_id' => $this->variant2->id, 'quantity' => 1],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'PENDIENTE_PAGO')
        ->assertJsonPath('summary.subtotal', 4450)
        ->assertJsonPath('summary.shipping_fee', 80)
        ->assertJsonPath('summary.total_net', 4530)
        ->assertJsonStructure([
            'order_id',
            'order_number',
            'status',
            'customer' => ['full_name', 'whatsapp_phone'],
            'summary' => ['subtotal', 'auto_discount_total', 'coupon_discount_total', 'shipping_fee', 'total_net'],
            'whatsapp_redirect_url',
            'created_at',
        ]);

    $this->assertDatabaseHas('orders', [
        'tenant_id' => $this->tenant->id,
        'status' => 'PENDIENTE_PAGO',
        'total_net' => 4530.00,
    ]);

    expect($this->variant1->fresh()->stock)->toBe(8)
        ->and($this->variant2->fresh()->stock)->toBe(4);

    $this->assertDatabaseCount('order_items', 2);
    $this->assertDatabaseHas('order_items', [
        'variant_id' => $this->variant1->id,
        'quantity' => 2,
        'unit_price' => 1450.00,
        'line_total' => 2900.00,
    ]);
});

test('genera url de whatsapp valida con el desglose de importes y datos bancarios', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo',
            'reference' => 'Frente al parque',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(201);
    $url = $response->json('whatsapp_redirect_url');

    expect($url)->toStartWith('https://wa.me/50499887766?text=')
        ->and($url)->toContain(rawurlencode($response->json('order_number')))
        ->and($url)->toContain(rawurlencode('Banco Ficohsa'))
        ->and($url)->toContain(rawurlencode('2000-1234-5678'));
});

test('atribuye comision en estado RETENIDA al afiliado cuando la orden usa un cupon referenciado', function () {
    $affiliate = Affiliate::factory()->create([
        'tenant_id' => $this->tenant->id,
        'commission_type' => 'PERCENTAGE',
        'commission_value' => 10.00,
        'is_active' => true,
    ]);

    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'COUPON',
        'discount_type' => 'PERCENTAGE',
        'discount_value' => 5.00,
        'allow_stacking' => false,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $coupon = Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'affiliate_id' => $affiliate->id,
        'code' => 'INFLUENCER10',
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'customer_info' => [
            'full_name' => 'Ana Gomez',
            'whatsapp_phone' => '+50499112233',
            'email' => 'ana@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Barrio Guamilito',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 2],
        ],
        'coupon_code' => 'INFLUENCER10',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(201);
    $orderId = $response->json('order_id');

    $this->assertDatabaseHas('commissions', [
        'tenant_id' => $this->tenant->id,
        'order_id' => $orderId,
        'affiliate_id' => $affiliate->id,
        'coupon_id' => $coupon->id,
        'amount' => 290.00,
        'status' => 'RETENIDA',
    ]);
});

test('rechaza con 422 peticiones de checkout que omitan la cabecera Idempotency-Key', function () {
    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['idempotency_key']);
});

test('retorna la orden existente sin descontar stock dos veces ante peticiones repetidas con la misma Idempotency-Key', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 2],
        ],
    ];

    $firstResponse = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $firstResponse->assertStatus(201);
    $firstOrderId = $firstResponse->json('order_id');

    expect($this->variant1->fresh()->stock)->toBe(8);

    $replayResponse = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $replayResponse->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->assertJsonPath('order_id', $firstOrderId);

    expect($this->variant1->fresh()->stock)->toBe(8);
    $this->assertDatabaseCount('orders', 1);
});

test('aborta con 409 Conflict y revierte la transaccion cuando el stock es insuficiente', function () {
    $this->variant1->update(['stock' => 1]);

    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 2],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');

    $this->assertDatabaseCount('orders', 0);
    expect($this->variant1->fresh()->stock)->toBe(1);
});

test('rechaza con 409 cuando se intenta procesar una peticion con clave de idempotencia en vuelo', function () {
    $idempotencyKey = (string) Str::uuid();
    $lockKey = "idempotency_lock:{$this->tenant->id}:{$idempotencyKey}";

    $lock = Cache::lock($lockKey, 30);
    $lock->acquire();

    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'TRANSACTION_IN_PROGRESS');

    $lock->release();
});

test('incrementa atomicamente el contador used_count del cupon aplicado', function () {
    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'COUPON',
        'discount_type' => 'FIXED_AMOUNT',
        'discount_value' => 100.00,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $coupon = Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'code' => 'DESCUENTO100',
        'used_count' => 0,
        'max_uses' => 10,
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
        'coupon_code' => 'DESCUENTO100',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(201);
    expect($coupon->fresh()->used_count)->toBe(1);
});

test('preserva snapshot inmutable aun cuando el producto base o variante sea modificada o borrada posteriormente', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'whatsapp_phone' => '+50498765432',
            'email' => 'carlos@correo.com',
        ],
        'shipping_address' => [
            'region_code' => 'HN-CR',
            'address_line' => 'Col. Trejo',
        ],
        'items' => [
            ['variant_id' => $this->variant1->id, 'quantity' => 1],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-checkout')
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/v1/storefront/checkout', $payload);

    $response->assertStatus(201);
    $orderId = $response->json('order_id');

    $this->product->update(['base_price' => 99999.00, 'name' => 'Nombre Cambiado']);
    $this->variant1->delete();

    $orderItem = OrderItem::where('order_id', $orderId)->first();
    expect((float) $orderItem->unit_price)->toBe(1450.00)
        ->and($orderItem->variant_snapshot['sku'])->toBe('OXF-39-NEG')
        ->and($orderItem->variant_snapshot['product_name'])->toBe('Zapato Oxford Clásico');
});
