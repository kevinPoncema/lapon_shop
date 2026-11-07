<?php

use App\Models\Order;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create([
        'slug' => 'tienda-partner-a',
        'is_active' => true,
    ]);

    $this->adminA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'slug' => 'tienda-partner-b',
        'is_active' => true,
    ]);

    $this->adminB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->configA = TenantConfig::factory()->create([
        'tenant_id' => $this->tenantA->id,
    ]);
});

test('administrador registra un comercio aliado hasheando portal_password y ocultandolo en la respuesta JSON', function () {
    Sanctum::actingAs($this->adminA);

    $payload = [
        'name' => 'Cafetería Gourmet',
        'portal_email' => 'cajero@cafegourmet.com',
        'portal_password' => 'Secreto123!',
        'benefit_title' => '20% de descuento en repostería',
        'terms_conditions' => 'Válido de lunes a viernes en local.',
        'contact_info' => [
            'address' => 'Mall Multiplaza, Nivel 2',
            'phone' => '+50499887766',
        ],
        'is_active' => true,
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->postJson('/api/v1/admin/partner-stores', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Cafetería Gourmet')
        ->assertJsonPath('data.portal_email', 'cajero@cafegourmet.com')
        ->assertJsonPath('data.benefit_title', '20% de descuento en repostería')
        ->assertJsonMissing(['portal_password']);

    $created = PartnerStore::query()
        ->where('portal_email', 'cajero@cafegourmet.com')
        ->first();

    expect($created)->not->toBeNull()
        ->and(Hash::check('Secreto123!', $created->portal_password))->toBeTrue();
});

test('administrador lista sus comercios aliados con metricas de vouchers emitidos y canjeados', function () {
    Sanctum::actingAs($this->adminA);

    $partner = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Gimnasio Titán',
    ]);

    $order1 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);
    $order2 = Order::factory()->create(['tenant_id' => $this->tenantA->id]);

    OutboundReward::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'partner_store_id' => $partner->id,
        'order_id' => $order1->id,
        'code' => 'RWD-EMIT-0001',
        'status' => 'EMITIDO',
    ]);

    OutboundReward::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'partner_store_id' => $partner->id,
        'order_id' => $order2->id,
        'code' => 'RWD-CANJ-0002',
        'status' => 'CANJEADO',
        'redeemed_at' => now(),
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->getJson('/api/v1/admin/partner-stores');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Gimnasio Titán')
        ->assertJsonPath('data.0.vouchers_count', 2)
        ->assertJsonPath('data.0.redeemed_count', 1);
});

test('administrador actualiza datos de un comercio aliado sin alterar la contrasena cuando no se envia', function () {
    Sanctum::actingAs($this->adminA);

    $partner = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Spa Relax',
        'portal_password' => Hash::make('ClaveOriginal!'),
        'benefit_title' => 'Masaje 15 min',
    ]);

    $originalPasswordHash = $partner->portal_password;

    $payload = [
        'name' => 'Spa Relax VIP',
        'portal_email' => $partner->portal_email,
        'benefit_title' => 'Masaje 30 min facial',
        'terms_conditions' => 'Previa cita telefónica.',
        'is_active' => true,
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson("/api/v1/admin/partner-stores/{$partner->id}", $payload);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Spa Relax VIP')
        ->assertJsonPath('data.benefit_title', 'Masaje 30 min facial');

    $fresh = $partner->fresh();
    expect($fresh->portal_password)->toBe($originalPasswordHash);
});

test('administrador configura contact_info bancario y reglas outbound_reward_rules con aliado activo', function () {
    Sanctum::actingAs($this->adminA);

    $partner = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    // 1. Configurar contact-info
    $contactPayload = [
        'whatsapp_sales' => '+50499001122',
        'support_email' => 'soporte@tienda.com',
        'bank_accounts' => [
            [
                'bank_name' => 'BAC Credomatic',
                'account_number' => '740192831',
                'account_type' => 'Cheques',
                'holder_name' => 'Tienda Alpha S. de R.L.',
            ],
        ],
    ];

    $responseContact = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson('/api/v1/admin/config/contact-info', $contactPayload);

    $responseContact->assertOk()
        ->assertJsonPath('contact_info.whatsapp_sales', '+50499001122')
        ->assertJsonPath('contact_info.bank_accounts.0.bank_name', 'BAC Credomatic');

    // 2. Configurar outbound-rewards
    $rewardPayload = [
        'is_enabled' => true,
        'partner_store_id' => $partner->id,
        'min_order_amount' => 1000.00,
        'validity_days' => 45,
    ];

    $responseRules = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson('/api/v1/admin/config/outbound-rewards', $rewardPayload);

    $responseRules->assertOk()
        ->assertJsonPath('outbound_reward_rules.is_enabled', true)
        ->assertJsonPath('outbound_reward_rules.partner_store_id', $partner->id)
        ->assertJsonPath('outbound_reward_rules.validity_days', 45);
});

test('rechaza con 422 registrar dos comercios aliados con el mismo portal_email en el mismo tenant', function () {
    Sanctum::actingAs($this->adminA);

    PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'portal_email' => 'repetido@aliado.com',
    ]);

    $payload = [
        'name' => 'Segundo Aliado',
        'portal_email' => 'repetido@aliado.com',
        'portal_password' => 'Secreto123!',
        'benefit_title' => 'Corte gratis',
        'terms_conditions' => 'Términos',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->postJson('/api/v1/admin/partner-stores', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['portal_email']);
});

test('permite usar el mismo portal_email en dos tenants distintos respetando el aislamiento multi-tenant', function () {
    PartnerStore::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'portal_email' => 'franquicia@comercio.com',
    ]);

    Sanctum::actingAs($this->adminA);

    $payload = [
        'name' => 'Aliado Tenant A',
        'portal_email' => 'franquicia@comercio.com',
        'portal_password' => 'ClaveTenantA123!',
        'benefit_title' => 'Beneficio Tenant A',
        'terms_conditions' => 'Términos',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->postJson('/api/v1/admin/partner-stores', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.portal_email', 'franquicia@comercio.com');
});

test('rechaza con 422 configurar outbound_reward_rules apuntando a un PartnerStore inactivo o de otro tenant', function () {
    Sanctum::actingAs($this->adminA);

    $inactivePartner = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'is_active' => false,
    ]);

    $partnerTenantB = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    // Intento 1: Aliado inactivo del mismo tenant
    $responseInactive = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson('/api/v1/admin/config/outbound-rewards', [
            'is_enabled' => true,
            'partner_store_id' => $inactivePartner->id,
            'min_order_amount' => 500.00,
            'validity_days' => 30,
        ]);
    $responseInactive->assertStatus(422);

    // Intento 2: Aliado activo pero de otro tenant
    $responseForeign = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson('/api/v1/admin/config/outbound-rewards', [
            'is_enabled' => true,
            'partner_store_id' => $partnerTenantB->id,
            'min_order_amount' => 500.00,
            'validity_days' => 30,
        ]);
    $responseForeign->assertStatus(422);
});

test('administrador del Tenant A no puede listar ni editar comercios aliados del Tenant B', function () {
    $partnerB = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Aliado Solo Tenant B',
    ]);

    Sanctum::actingAs($this->adminA);

    // Listado en Tenant A no incluye aliado de Tenant B
    $responseList = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->getJson('/api/v1/admin/partner-stores');
    $responseList->assertOk()
        ->assertJsonMissing(['name' => 'Aliado Solo Tenant B']);

    // Edición con ID de B en Tenant A devuelve 404
    $responseEdit = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson("/api/v1/admin/partner-stores/{$partnerB->id}", [
            'name' => 'Intento Hack',
            'portal_email' => 'hack@aliado.com',
            'benefit_title' => 'Beneficio Hack',
            'terms_conditions' => 'Términos',
        ]);
    $responseEdit->assertStatus(404);
});

test('desactivar un comercio aliado o rotar su contrasena revoca todos sus tokens activos de Sanctum', function () {
    Sanctum::actingAs($this->adminA);

    $partner = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    // Crear token de Sanctum para el aliado
    $token = $partner->createToken('partner-device', ['partner-portal']);
    expect($partner->tokens()->count())->toBe(1);

    // Desactivar el aliado
    $response = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson("/api/v1/admin/partner-stores/{$partner->id}", [
            'name' => $partner->name,
            'portal_email' => $partner->portal_email,
            'benefit_title' => $partner->benefit_title,
            'terms_conditions' => $partner->terms_conditions,
            'is_active' => false,
        ]);

    $response->assertOk();
    expect($partner->fresh()->tokens()->count())->toBe(0);
});

test('permite deshabilitar outbound_reward_rules con is_enabled false sin exigir umbrales ni aliado', function () {
    Sanctum::actingAs($this->adminA);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-partner-a')
        ->putJson('/api/v1/admin/config/outbound-rewards', [
            'is_enabled' => false,
        ]);

    $response->assertOk()
        ->assertJsonPath('outbound_reward_rules.is_enabled', false);
});
