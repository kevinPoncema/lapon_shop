<?php

use App\Models\Order;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Tenant;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create([
        'name' => 'Calzados Elegantes',
        'slug' => 'calzados-hn',
        'is_active' => true,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'name' => 'Tienda B',
        'slug' => 'tienda-b-partner',
        'is_active' => true,
    ]);

    $this->partnerA1 = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Cafetería Aroma',
        'benefit_title' => '20% de descuento en café y postre',
        'terms_conditions' => 'Válido de lunes a viernes en consumo en salón.',
        'is_active' => true,
    ]);

    $this->partnerA2 = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Gimnasio Titan',
        'benefit_title' => 'Pase de 1 día gratis',
        'is_active' => true,
    ]);

    $this->partnerB = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Restaurante Tenant B',
        'is_active' => true,
    ]);

    $this->order = Order::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'email' => 'carlos@privado.com',
            'whatsapp_phone' => '+50499887766',
        ],
        'shipping_address' => [
            'address_line' => 'Col. Trejo, 10 Calle',
            'region_code' => 'HN-CR',
        ],
        'total_net' => 1250.00,
    ]);

    $this->reward = OutboundReward::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'partner_store_id' => $this->partnerA1->id,
        'order_id' => $this->order->id,
        'code' => 'RWD-9F8A-7C2B-4E1D',
        'status' => 'EMITIDO',
        'expires_at' => now()->addDays(30),
        'redeemed_at' => null,
    ]);
});

test('comercio aliado autenticado consulta un voucher EMITIDO vigente obteniendo beneficio y nombre del cliente', function () {
    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    $response = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->getJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D');

    $response->assertOk()
        ->assertJsonPath('voucher_code', 'RWD-9F8A-7C2B-4E1D')
        ->assertJsonPath('status', 'EMITIDO')
        ->assertJsonPath('is_redeemable', true)
        ->assertJsonPath('benefit_title', '20% de descuento en café y postre')
        ->assertJsonPath('terms_conditions', 'Válido de lunes a viernes en consumo en salón.')
        ->assertJsonPath('customer_name', 'Carlos Mendoza')
        ->assertJsonPath('origin_tenant_name', 'Calzados Elegantes')
        ->assertJsonPath('redeemed_at', null);
});

test('comercio aliado canjea exitosamente un voucher vigente cambiando su estado a CANJEADO y fijando redeemed_at', function () {
    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    $response = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->postJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D/redeem');

    $response->assertOk()
        ->assertJsonPath('voucher_code', 'RWD-9F8A-7C2B-4E1D')
        ->assertJsonPath('status', 'CANJEADO')
        ->assertJsonPath('message', 'Beneficio canjeado exitosamente.');

    $freshReward = $this->reward->fresh();
    expect($freshReward->status)->toBe('CANJEADO')
        ->and($freshReward->redeemed_at)->not->toBeNull();
});

test('respuesta de consulta de voucher jamas expone email telefono direccion ni montos de la orden original', function () {
    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    $response = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->getJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D');

    $response->assertOk()
        ->assertJsonMissing([
            'email',
            'carlos@privado.com',
            'whatsapp_phone',
            '+50499887766',
            'address_line',
            'total_net',
            '1250.00',
        ]);
});

test('segundo intento de canjear el mismo voucher retorna 409 VOUCHER_ALREADY_REDEEMED preservando el redeemed_at original', function () {
    $redeemedAt = Carbon::parse('2026-11-06 20:15:30');
    $this->reward->update([
        'status' => 'CANJEADO',
        'redeemed_at' => $redeemedAt,
    ]);

    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    $response = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->postJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D/redeem');

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'VOUCHER_ALREADY_REDEEMED')
        ->assertJsonPath('error.redeemed_at', $redeemedAt->toISOString());

    expect($this->reward->fresh()->redeemed_at->toISOString())->toBe($redeemedAt->toISOString());
});

test('intentar canjear un voucher cuya fecha expires_at ya vencio retorna 422 VOUCHER_EXPIRED', function () {
    $expiredAt = Carbon::parse('2026-10-15 23:59:59');
    $this->reward->update([
        'status' => 'EMITIDO',
        'expires_at' => $expiredAt,
    ]);

    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    $response = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->postJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D/redeem');

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VOUCHER_EXPIRED')
        ->assertJsonPath('error.expires_at', $expiredAt->toISOString());

    expect($this->reward->fresh()->status)->toBe('EXPIRADO');
});

test('consultar un voucher con expires_at en el pasado retorna is_redeemable false y estado efectivo EXPIRADO', function () {
    $this->reward->update([
        'status' => 'EMITIDO',
        'expires_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    $response = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->getJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D');

    $response->assertOk()
        ->assertJsonPath('is_redeemable', false)
        ->assertJsonPath('status', 'EXPIRADO');
});

test('Comercio Aliado A recibe 404 al intentar consultar o canjear un voucher emitido para el Comercio Aliado B del mismo tenant', function () {
    // Autenticado como partnerA2 (Titan) intentando ver voucher emitido para partnerA1 (Aroma)
    Sanctum::actingAs($this->partnerA2, ['partner-portal']);

    // Consulta
    $responseGet = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->getJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D');
    $responseGet->assertStatus(404);

    // Canje
    $responsePost = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->postJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D/redeem');
    $responsePost->assertStatus(404);

    expect($this->reward->fresh()->status)->toBe('EMITIDO');
});

test('Comercio Aliado del Tenant A recibe 403 o 404 al operar con X-Tenant-Slug del Tenant B', function () {
    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-b-partner')
        ->getJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D');

    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('busqueda y canje normalizan el codigo de voucher en minusculas o con espacios laterales', function () {
    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    // Consulta con minúsculas y espacios
    $responseGet = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->getJson('/api/v1/partner-portal/vouchers/%20%20rwd-9f8a-7c2b-4e1d%20%20');
    $responseGet->assertOk()
        ->assertJsonPath('voucher_code', 'RWD-9F8A-7C2B-4E1D');

    // Canje con minúsculas
    $responsePost = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->postJson('/api/v1/partner-portal/vouchers/rwd-9f8a-7c2b-4e1d/redeem');
    $responsePost->assertOk()
        ->assertJsonPath('status', 'CANJEADO');
});

test('bloqueo pesimista lockForUpdate garantiza que ante peticiones concurrentes exactamente una canjee y la otra reciba 409', function () {
    Sanctum::actingAs($this->partnerA1, ['partner-portal']);

    // Primera petición canjea con éxito
    $response1 = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->postJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D/redeem');
    $response1->assertOk()
        ->assertJsonPath('status', 'CANJEADO');

    // Segunda petición inmediata choca con estado CANJEADO y recibe 409
    $response2 = $this->withHeader('X-Tenant-Slug', 'calzados-hn')
        ->postJson('/api/v1/partner-portal/vouchers/RWD-9F8A-7C2B-4E1D/redeem');
    $response2->assertStatus(409)
        ->assertJsonPath('error.code', 'VOUCHER_ALREADY_REDEEMED');
});
