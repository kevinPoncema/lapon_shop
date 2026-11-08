<?php

use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerStore;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-admin-orders',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->config = TenantConfig::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outbound_reward_rules' => [
            'is_enabled' => true,
            'min_order_total' => 1000.00,
            'expiration_days' => 30,
        ],
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Camisa Lino',
        'base_price' => 1200.00,
    ]);

    $this->variant = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'sku' => 'LIN-BLA-M',
        'stock' => 10,
    ]);
});

test('administrador lista ordenes con filtros de estado y busqueda', function () {
    Sanctum::actingAs($this->admin);

    $order1 = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_number' => '#ORD-AAAA11',
        'status' => 'PENDIENTE_PAGO',
        'customer_info' => ['full_name' => 'Mario Bros', 'whatsapp_phone' => '+50499001122'],
    ]);

    $order2 = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_number' => '#ORD-BBBB22',
        'status' => 'COMPLETADA',
        'customer_info' => ['full_name' => 'Luigi Bros', 'whatsapp_phone' => '+50499003344'],
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-admin-orders')
        ->getJson('/api/v1/admin/orders?status=PENDIENTE_PAGO');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', '#ORD-AAAA11');

    $responseSearch = $this->withHeader('X-Tenant-Slug', 'tienda-admin-orders')
        ->getJson('/api/v1/admin/orders?search=Luigi');

    $responseSearch->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', '#ORD-BBBB22');
});

test('administrador consulta detalle exhaustivo de la orden con snapshot y comision', function () {
    Sanctum::actingAs($this->admin);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_number' => '#ORD-DETALLE1',
        'status' => 'PENDIENTE_PAGO',
    ]);

    $item = OrderItem::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'variant_id' => $this->variant->id,
        'quantity' => 2,
        'unit_price' => 1200.00,
        'line_total' => 2400.00,
        'variant_snapshot' => [
            'sku' => 'LIN-BLA-M',
            'product_name' => 'Camisa Lino',
        ],
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-admin-orders')
        ->getJson("/api/v1/admin/orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.order_number', '#ORD-DETALLE1')
        ->assertJsonPath('data.items.0.variant_snapshot.sku', 'LIN-BLA-M');
});

test('administrador actualiza estado a COMPLETADA aprobando comisiones y emitiendo recompensa aliada', function () {
    Sanctum::actingAs($this->admin);

    $partnerStore = PartnerStore::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Restaurante Gourmet',
        'is_active' => true,
    ]);

    $affiliate = Affiliate::factory()->create([
        'tenant_id' => $this->tenant->id,
        'commission_type' => 'PERCENTAGE',
        'commission_value' => 10.00,
        'is_active' => true,
    ]);

    $promo = Promotion::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'COUPON']);
    $coupon = Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'affiliate_id' => $affiliate->id,
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'PENDIENTE_PAGO',
        'subtotal' => 1500.00,
        'total_net' => 1500.00,
        'applied_coupon_id' => $coupon->id,
    ]);

    $commission = Commission::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => $order->id,
        'affiliate_id' => $affiliate->id,
        'coupon_id' => $coupon->id,
        'amount' => 150.00,
        'status' => 'RETENIDA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-admin-orders')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'COMPLETADA',
            'payment_notes' => 'Transferencia verificada en BAC',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'COMPLETADA');

    expect($commission->fresh()->status)->toBe('APROBADA');

    $this->assertDatabaseHas('outbound_rewards', [
        'tenant_id' => $this->tenant->id,
        'order_id' => $order->id,
        'partner_store_id' => $partnerStore->id,
        'status' => 'EMITIDO',
    ]);
});

test('administrador actualiza estado a CANCELADA revirtiendo stock y anulando comisiones', function () {
    Sanctum::actingAs($this->admin);

    $this->variant->update(['stock' => 5]);

    $promo = Promotion::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'COUPON']);
    $coupon = Coupon::factory()->create([
        'tenant_id' => $this->tenant->id,
        'promotion_id' => $promo->id,
        'used_count' => 1,
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'PENDIENTE_PAGO',
        'applied_coupon_id' => $coupon->id,
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => $order->id,
        'variant_id' => $this->variant->id,
        'quantity' => 3,
    ]);

    $commission = Commission::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => $order->id,
        'coupon_id' => $coupon->id,
        'status' => 'RETENIDA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-admin-orders')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'CANCELADA',
            'payment_notes' => 'Cliente canceló el pedido',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'CANCELADA');

    expect($this->variant->fresh()->stock)->toBe(8)
        ->and($coupon->fresh()->used_count)->toBe(0)
        ->and($commission->fresh()->status)->toBe('ANULADA');
});

test('rechaza con 409 intentos de transicion desde estados finales', function () {
    Sanctum::actingAs($this->admin);

    $completedOrder = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'COMPLETADA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-admin-orders')
        ->patchJson("/api/v1/admin/orders/{$completedOrder->id}/status", [
            'status' => 'CANCELADA',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_ORDER_STATUS_TRANSITION');
});

test('administrador de tenant A no puede ver ni modificar ordenes de tenant B', function () {
    $tenantB = Tenant::factory()->create(['slug' => 'tienda-b-orders', 'is_active' => true]);
    $orderB = Order::factory()->create(['tenant_id' => $tenantB->id]);

    Sanctum::actingAs($this->admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-b-orders')
        ->getJson("/api/v1/admin/orders/{$orderB->id}");

    $response->assertStatus(403);
});
