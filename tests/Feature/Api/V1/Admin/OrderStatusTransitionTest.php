<?php

use App\Jobs\SendOutboundRewardVoucherJob;
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
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create([
        'slug' => 'tienda-status-a',
        'is_active' => true,
    ]);

    $this->adminA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'slug' => 'tienda-status-b',
        'is_active' => true,
    ]);

    $this->adminB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->partnerStore = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Cafetería Aliada',
        'benefit_title' => '2x1 en capuchinos',
        'is_active' => true,
    ]);

    $this->configA = TenantConfig::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'outbound_reward_rules' => [
            'is_enabled' => true,
            'partner_store_id' => $this->partnerStore->id,
            'min_order_amount' => 1000.00,
            'validity_days' => 30,
        ],
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Camisa Lino',
        'base_price' => 1200.00,
    ]);

    $this->variant1 = ProductVariant::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'product_id' => $this->product->id,
        'sku' => 'LIN-BLA-M',
        'stock' => 10,
    ]);

    $this->variant2 = ProductVariant::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'product_id' => $this->product->id,
        'sku' => 'LIN-AZU-L',
        'stock' => 5,
    ]);
});

test('completar una orden pendiente fija completed_at aprueba la comision retenida y retorna 200', function () {
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
        'status' => 'PENDIENTE_PAGO',
        'subtotal' => 1200.00,
        'total_net' => 1200.00,
        'applied_coupon_id' => $coupon->id,
    ]);

    $commission = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'affiliate_id' => $affiliate->id,
        'coupon_id' => $coupon->id,
        'amount' => 120.00,
        'status' => 'RETENIDA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'COMPLETADA',
            'payment_notes' => 'Transferencia verificada en BAC',
        ]);

    $response->assertOk()
        ->assertJsonPath('order_id', $order->id)
        ->assertJsonPath('status', 'COMPLETADA')
        ->assertJsonPath('commission_status', 'APROBADA')
        ->assertJsonPath('outbound_reward_generated', true);

    expect($commission->fresh()->status)->toBe('APROBADA');
    expect($order->fresh()->completed_at)->not->toBeNull();
});

test('completar una orden que supera el umbral emite voucher en outbound_rewards y encola SendOutboundRewardVoucherJob', function () {
    Queue::fake();
    Sanctum::actingAs($this->adminA);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
        'subtotal' => 1500.00,
        'total_net' => 1500.00,
        'customer_info' => [
            'full_name' => 'Roberto Carlos',
            'email' => 'roberto@example.com',
            'whatsapp_phone' => '+50499008877',
        ],
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'COMPLETADA',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'COMPLETADA')
        ->assertJsonPath('outbound_reward_generated', true);

    $this->assertDatabaseHas('outbound_rewards', [
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'partner_store_id' => $this->partnerStore->id,
        'status' => 'EMITIDO',
    ]);

    Queue::assertPushed(SendOutboundRewardVoucherJob::class);
});

test('completar una orden sin comision ni umbral alcanzado finaliza exitosamente con outbound_reward_generated en false', function () {
    Sanctum::actingAs($this->adminA);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
        'subtotal' => 500.00,
        'total_net' => 500.00, // Umbral configurado es 1000.00
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'COMPLETADA',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'COMPLETADA')
        ->assertJsonPath('outbound_reward_generated', false)
        ->assertJsonPath('commission_status', null);

    $this->assertDatabaseMissing('outbound_rewards', [
        'order_id' => $order->id,
    ]);
});

test('cancelar una orden pendiente restituye exactamente el stock descontado en todas las variantes involucradas', function () {
    Sanctum::actingAs($this->adminA);

    $this->variant1->update(['stock' => 8]);
    $this->variant2->update(['stock' => 3]);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'variant_id' => $this->variant1->id,
        'quantity' => 2,
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'variant_id' => $this->variant2->id,
        'quantity' => 2,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'CANCELADA',
            'cancellation_reason' => 'Cliente no envió comprobante',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'CANCELADA');

    expect($this->variant1->fresh()->stock)->toBe(10)
        ->and($this->variant2->fresh()->stock)->toBe(5);
});

test('cancelar una orden con cupon aplicado decrementa used_count del cupon y cambia la comision a ANULADA', function () {
    Sanctum::actingAs($this->adminA);

    $promo = Promotion::factory()->create(['tenant_id' => $this->tenantA->id, 'type' => 'COUPON']);
    $coupon = Coupon::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'promotion_id' => $promo->id,
        'used_count' => 1,
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
        'applied_coupon_id' => $coupon->id,
    ]);

    $commission = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'coupon_id' => $coupon->id,
        'status' => 'RETENIDA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'CANCELADA',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'CANCELADA')
        ->assertJsonPath('commission_status', 'ANULADA');

    expect($coupon->fresh()->used_count)->toBe(0)
        ->and($commission->fresh()->status)->toBe('ANULADA');
});

test('rechaza con 409 Conflict cualquier intento de cancelar una orden que ya estaba COMPLETADA sin alterar el stock', function () {
    Sanctum::actingAs($this->adminA);

    $this->variant1->update(['stock' => 10]);

    $completedOrder = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'COMPLETADA',
        'completed_at' => now()->subDay(),
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $completedOrder->id,
        'variant_id' => $this->variant1->id,
        'quantity' => 2,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$completedOrder->id}/status", [
            'status' => 'CANCELADA',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_ORDER_STATUS_TRANSITION');

    // Stock permanece inalterado
    expect($this->variant1->fresh()->stock)->toBe(10);
});

test('rechaza con 409 Conflict cualquier intento de completar o volver a cancelar una orden ya CANCELADA evitando duplicar restitucion de stock', function () {
    Sanctum::actingAs($this->adminA);

    $this->variant1->update(['stock' => 10]);

    $cancelledOrder = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'CANCELADA',
        'cancelled_at' => now()->subDay(),
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $cancelledOrder->id,
        'variant_id' => $this->variant1->id,
        'quantity' => 3,
    ]);

    // Intento 1: Completar una ya cancelada
    $responseComplete = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$cancelledOrder->id}/status", [
            'status' => 'COMPLETADA',
        ]);
    $responseComplete->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_ORDER_STATUS_TRANSITION');

    // Intento 2: Volver a cancelar una ya cancelada (previniendo doble restitución)
    $responseReCancel = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$cancelledOrder->id}/status", [
            'status' => 'CANCELADA',
        ]);
    $responseReCancel->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_ORDER_STATUS_TRANSITION');

    expect($this->variant1->fresh()->stock)->toBe(10);
});

test('rechaza con 422 estados no permitidos como PENDIENTE_PAGO o valores arbitrarios', function () {
    Sanctum::actingAs($this->adminA);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
    ]);

    $responsePending = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'PENDIENTE_PAGO',
        ]);
    $responsePending->assertStatus(422);

    $responseArbitrary = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'APROBADA_INVENTADA',
        ]);
    $responseArbitrary->assertStatus(422);
});

test('administrador del Tenant A no puede cambiar el estado de una orden del Tenant B manteniendo su stock y comisiones intactas', function () {
    Sanctum::actingAs($this->adminA);

    $variantB = ProductVariant::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'stock' => 5,
    ]);

    $orderB = Order::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'status' => 'PENDIENTE_PAGO',
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'order_id' => $orderB->id,
        'variant_id' => $variantB->id,
        'quantity' => 2,
    ]);

    // Con cabecera de Tenant A intentando tocar ID de Tenant B -> 404
    $responseA = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$orderB->id}/status", [
            'status' => 'CANCELADA',
        ]);
    $responseA->assertStatus(404);

    // Con cabecera de Tenant B siendo Admin A -> 403 Forbidden
    $responseB = $this->withHeader('X-Tenant-Slug', 'tienda-status-b')
        ->patchJson("/api/v1/admin/orders/{$orderB->id}/status", [
            'status' => 'CANCELADA',
        ]);
    $responseB->assertStatus(403);

    expect($variantB->fresh()->stock)->toBe(5)
        ->and($orderB->fresh()->status)->toBe('PENDIENTE_PAGO');
});

test('cancelar una orden cuya variante fue eliminada con SoftDeletes restituye igualmente el stock sin reactivar la variante', function () {
    Sanctum::actingAs($this->adminA);

    $this->variant1->update(['stock' => 2, 'is_active' => false]);
    $this->variant1->delete(); // Soft delete

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
    ]);

    OrderItem::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'order_id' => $order->id,
        'variant_id' => $this->variant1->id,
        'quantity' => 3,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'CANCELADA',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'CANCELADA');

    $trashedVariant = ProductVariant::withTrashed()->find($this->variant1->id);
    expect($trashedVariant->stock)->toBe(5)
        ->and($trashedVariant->trashed())->toBeTrue()
        ->and($trashedVariant->is_active)->toBeFalse();
});

test('no emite voucher cruzado cuando el PartnerStore configurado esta inactivo o pertenece a otro tenant', function () {
    Sanctum::actingAs($this->adminA);

    // Desactivamos el partner
    $this->partnerStore->update(['is_active' => false]);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
        'subtotal' => 2000.00,
        'total_net' => 2000.00,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'COMPLETADA',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'COMPLETADA')
        ->assertJsonPath('outbound_reward_generated', false);

    $this->assertDatabaseMissing('outbound_rewards', [
        'order_id' => $order->id,
    ]);
});

test('decremento de used_count en cupon al cancelar nunca produce valores negativos si el contador estaba en cero', function () {
    Sanctum::actingAs($this->adminA);

    $promo = Promotion::factory()->create(['tenant_id' => $this->tenantA->id, 'type' => 'COUPON']);
    $coupon = Coupon::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'promotion_id' => $promo->id,
        'used_count' => 0, // En cero
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'status' => 'PENDIENTE_PAGO',
        'applied_coupon_id' => $coupon->id,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-status-a')
        ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'CANCELADA',
        ]);

    $response->assertOk();
    expect($coupon->fresh()->used_count)->toBe(0);
});
