<?php

use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create([
        'slug' => 'tienda-comm-a',
        'is_active' => true,
    ]);

    $this->adminA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'slug' => 'tienda-comm-b',
        'is_active' => true,
    ]);

    $this->adminB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->affiliateA = Affiliate::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Afiliado Carlos',
        'is_active' => true,
    ]);

    $this->promoA = Promotion::factory()->create(['tenant_id' => $this->tenantA->id, 'type' => 'COUPON']);
    $this->couponA = Coupon::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'promotion_id' => $this->promoA->id,
        'affiliate_id' => $this->affiliateA->id,
    ]);
});

test('administrador consulta el estado de cuenta de un afiliado con desglose de totales retenidos aprobados y pagados', function () {
    Sanctum::actingAs($this->adminA);

    $order1 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);
    $order2 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);
    $order3 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);

    Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order1->id,
        'coupon_id' => $this->couponA->id,
        'amount' => 100.00,
        'status' => 'RETENIDA',
    ]);

    Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order2->id,
        'coupon_id' => $this->couponA->id,
        'amount' => 200.00,
        'status' => 'APROBADA',
    ]);

    Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order3->id,
        'coupon_id' => $this->couponA->id,
        'amount' => 150.00,
        'status' => 'PAGADA',
        'payment_reference' => 'ACH-OLD-11',
        'paid_at' => now()->subDays(5),
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->getJson("/api/v1/admin/affiliates/{$this->affiliateA->id}/commissions");

    $response->assertOk()
        ->assertJsonPath('totals.retained', fn ($v) => (float) $v === 100.00)
        ->assertJsonPath('totals.approved', fn ($v) => (float) $v === 200.00)
        ->assertJsonPath('totals.paid', fn ($v) => (float) $v === 150.00)
        ->assertJsonCount(3, 'commissions.data');
});

test('liquida exitosamente un lote de comisiones APROBADAS marcandolas como PAGADA con paid_at y referencia bancaria', function () {
    Sanctum::actingAs($this->adminA);

    $order1 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);
    $order2 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);

    $comm1 = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order1->id,
        'coupon_id' => $this->couponA->id,
        'amount' => 150.00,
        'status' => 'APROBADA',
    ]);

    $comm2 = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order2->id,
        'coupon_id' => $this->couponA->id,
        'amount' => 200.00,
        'status' => 'APROBADA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->postJson("/api/v1/admin/affiliates/{$this->affiliateA->id}/commissions/settle", [
            'commission_ids' => [$comm1->id, $comm2->id],
            'payment_reference' => 'ACH-884920',
            'payment_receipt_url' => 'https://cdn.plataforma.com/receipts/ach-884920.pdf',
        ]);

    $response->assertOk()
        ->assertJsonPath('settled_count', 2)
        ->assertJsonPath('total_paid', fn ($v) => (float) $v === 350.00)
        ->assertJsonPath('status', 'PAGADA')
        ->assertJsonPath('payment_reference', 'ACH-884920');

    expect($comm1->fresh()->status)->toBe('PAGADA')
        ->and($comm1->fresh()->payment_reference)->toBe('ACH-884920')
        ->and($comm1->fresh()->paid_at)->not->toBeNull()
        ->and($comm2->fresh()->status)->toBe('PAGADA');
});

test('rechaza con 422 y revierte todo el lote si al menos una comision sigue en estado RETENIDA', function () {
    Sanctum::actingAs($this->adminA);

    $order1 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);
    $order2 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);

    $commApproved = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order1->id,
        'coupon_id' => $this->couponA->id,
        'amount' => 100.00,
        'status' => 'APROBADA',
    ]);

    $commRetained = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order2->id,
        'coupon_id' => $this->couponA->id,
        'amount' => 150.00,
        'status' => 'RETENIDA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->postJson("/api/v1/admin/affiliates/{$this->affiliateA->id}/commissions/settle", [
            'commission_ids' => [$commApproved->id, $commRetained->id],
            'payment_reference' => 'ACH-REV-01',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'COMMISSION_NOT_SETTLEABLE');

    // Integridad transaccional: ninguna debió cambiar a PAGADA
    expect($commApproved->fresh()->status)->toBe('APROBADA')
        ->and($commRetained->fresh()->status)->toBe('RETENIDA');
});

test('rechaza con 422 si se intenta volver a liquidar una comision que ya esta PAGADA o ANULADA', function () {
    Sanctum::actingAs($this->adminA);

    $order = Order::factory()->create(['tenant_id' => $this->tenantA->id]);

    $commPaid = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $this->affiliateA->id,
        'order_id' => $order->id,
        'coupon_id' => $this->couponA->id,
        'status' => 'PAGADA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->postJson("/api/v1/admin/affiliates/{$this->affiliateA->id}/commissions/settle", [
            'commission_ids' => [$commPaid->id],
            'payment_reference' => 'ACH-DUP-01',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'COMMISSION_NOT_SETTLEABLE');
});

test('rechaza con 422 o 404 si se envian commission_ids que pertenecen a otro afiliado del mismo tenant', function () {
    Sanctum::actingAs($this->adminA);

    $otherAffiliate = Affiliate::factory()->create(['tenant_id' => $this->tenantA->id]);
    $order = Order::factory()->create(['tenant_id' => $this->tenantA->id]);

    $commOther = Commission::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'affiliate_id' => $otherAffiliate->id,
        'order_id' => $order->id,
        'coupon_id' => $this->couponA->id,
        'status' => 'APROBADA',
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->postJson("/api/v1/admin/affiliates/{$this->affiliateA->id}/commissions/settle", [
            'commission_ids' => [$commOther->id],
            'payment_reference' => 'ACH-OTHER-01',
        ]);

    expect(in_array($response->status(), [404, 422]))->toBeTrue();
    expect($commOther->fresh()->status)->toBe('APROBADA');
});

test('aislamiento multi-tenant impide que el Tenant A liquide o consulte comisiones de afiliados del Tenant B', function () {
    Sanctum::actingAs($this->adminA);

    $affiliateB = Affiliate::factory()->create(['tenant_id' => $this->tenantB->id]);
    $orderB = Order::factory()->create(['tenant_id' => $this->tenantB->id]);
    $promoB = Promotion::factory()->create(['tenant_id' => $this->tenantB->id]);
    $couponB = Coupon::factory()->create(['tenant_id' => $this->tenantB->id, 'promotion_id' => $promoB->id]);

    $commB = Commission::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'affiliate_id' => $affiliateB->id,
        'order_id' => $orderB->id,
        'coupon_id' => $couponB->id,
        'status' => 'APROBADA',
    ]);

    // Con cabecera de Tenant A intentando ver afiliado de Tenant B -> 404
    $responseGet = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->getJson("/api/v1/admin/affiliates/{$affiliateB->id}/commissions");
    $responseGet->assertStatus(404);

    // Con cabecera de Tenant A intentando liquidar -> 404
    $responsePost = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->postJson("/api/v1/admin/affiliates/{$affiliateB->id}/commissions/settle", [
            'commission_ids' => [$commB->id],
            'payment_reference' => 'ACH-LEAK-01',
        ]);
    $responsePost->assertStatus(404);

    expect($commB->fresh()->status)->toBe('APROBADA');
});

test('rechaza con 422 peticiones con arreglo commission_ids vacio o sin payment_reference', function () {
    Sanctum::actingAs($this->adminA);

    $responseEmpty = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->postJson("/api/v1/admin/affiliates/{$this->affiliateA->id}/commissions/settle", [
            'commission_ids' => [],
            'payment_reference' => 'ACH-01',
        ]);
    $responseEmpty->assertStatus(422);

    $responseNoRef = $this->withHeader('X-Tenant-Slug', 'tienda-comm-a')
        ->postJson("/api/v1/admin/affiliates/{$this->affiliateA->id}/commissions/settle", [
            'commission_ids' => ['01a1b46c-0000-0000-0000-000000000000'],
        ]);
    $responseNoRef->assertStatus(422);
});
