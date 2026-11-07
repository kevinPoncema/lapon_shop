<?php

use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create([
        'slug' => 'tienda-alpha',
        'is_active' => true,
    ]);

    $this->adminA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'slug' => 'tienda-beta',
        'is_active' => true,
    ]);

    $this->adminB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Zapato Oxford',
        'base_price' => 1500.00,
    ]);

    $this->variant = ProductVariant::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'product_id' => $this->product->id,
        'sku' => 'OXF-NEG-40',
        'stock' => 10,
    ]);
});

test('administrador lista paginadamente las ordenes de su tienda filtrando por estado y rango de fechas', function () {
    Sanctum::actingAs($this->adminA);

    $order1 = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_number' => '#ORD-PEND-01',
        'status' => 'PENDIENTE_PAGO',
        'created_at' => '2026-11-01 10:00:00',
    ]);

    $order2 = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_number' => '#ORD-COMP-02',
        'status' => 'COMPLETADA',
        'created_at' => '2026-11-05 15:00:00',
    ]);

    $order3 = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_number' => '#ORD-PEND-03',
        'status' => 'PENDIENTE_PAGO',
        'created_at' => '2026-11-10 12:00:00',
    ]);

    // Filtrar por estado
    $responseStatus = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders?status=PENDIENTE_PAGO');

    $responseStatus->assertOk()
        ->assertJsonCount(2, 'data');

    // Filtrar por rango de fechas
    $responseDate = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders?date_from=2026-11-04&date_to=2026-11-06');

    $responseDate->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', '#ORD-COMP-02');
});

test('administrador consulta el detalle completo de una orden incluyendo snapshots comision y vouchers', function () {
    Sanctum::actingAs($this->adminA);

    $promo = Promotion::factory()->create(['tenant_id' => $this->tenantA->id, 'type' => 'COUPON']);
    $affiliate = Affiliate::factory()->create(['tenant_id' => $this->tenantA->id]);
    $coupon = Coupon::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'promotion_id' => $promo->id,
        'affiliate_id' => $affiliate->id,
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_number' => '#ORD-FULLDETAIL',
        'status' => 'COMPLETADA',
        'applied_coupon_id' => $coupon->id,
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'variant_id' => $this->variant->id,
        'quantity' => 2,
        'unit_price' => 1500.00,
        'line_total' => 3000.00,
        'variant_snapshot' => [
            'sku' => 'OXF-NEG-40',
            'product_name' => 'Zapato Oxford',
        ],
    ]);

    Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'affiliate_id' => $affiliate->id,
        'coupon_id' => $coupon->id,
        'amount' => 150.00,
        'status' => 'APROBADA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson("/api/v1/admin/orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.order_number', '#ORD-FULLDETAIL')
        ->assertJsonPath('data.items.0.variant_snapshot.sku', 'OXF-NEG-40')
        ->assertJsonPath('data.commission.amount', '150.00');
});

test('busqueda filtra ordenes por numero de orden o nombre del comprador en customer_info', function () {
    Sanctum::actingAs($this->adminA);

    Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_number' => '#ORD-MARIO-01',
        'customer_info' => ['full_name' => 'Mario Rossi', 'whatsapp_phone' => '+50499001122'],
    ]);

    Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_number' => '#ORD-LUIGI-02',
        'customer_info' => ['full_name' => 'Luigi Rossi', 'whatsapp_phone' => '+50499003344'],
    ]);

    // Búsqueda con parámetro q
    $responseQ = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders?q=Luigi');

    $responseQ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', '#ORD-LUIGI-02');

    // Búsqueda con parámetro search
    $responseSearch = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders?search=MARIO');

    $responseSearch->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', '#ORD-MARIO-01');
});

test('usuario no autenticado recibe 401 Unauthorized al consultar ordenes', function () {
    $response = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders');

    $response->assertStatus(401);
});

test('administrador del Tenant A recibe 404 o 403 al intentar ver el detalle de una orden del Tenant B', function () {
    Sanctum::actingAs($this->adminA);

    $orderB = Order::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'order_number' => '#ORD-TENANT-B',
    ]);

    // Intentando consultar directamente en tenant A (no existe en tenant A -> 404)
    $responseA = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson("/api/v1/admin/orders/{$orderB->id}");
    $responseA->assertStatus(404);

    // Intentando enviar cabecera del Tenant B (no pertenece al tenant B -> 403)
    $responseB = $this->withHeader('X-Tenant-Slug', 'tienda-beta')
        ->getJson("/api/v1/admin/orders/{$orderB->id}");
    $responseB->assertStatus(403);
});

test('listado de ordenes del Tenant A jamas incluye registros pertenecientes al Tenant B', function () {
    Sanctum::actingAs($this->adminA);

    Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_number' => '#ORD-A1',
    ]);

    Order::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'order_number' => '#ORD-B1',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', '#ORD-A1');
});

test('rechaza con 422 filtros con estados inexistentes o rango de fechas invertido', function () {
    Sanctum::actingAs($this->adminA);

    $responseBadStatus = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders?status=INVALID_STATUS');
    $responseBadStatus->assertStatus(422);

    $responseInvertedDates = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->getJson('/api/v1/admin/orders?date_from=2026-11-10&date_to=2026-11-01');
    $responseInvertedDates->assertStatus(422);
});
