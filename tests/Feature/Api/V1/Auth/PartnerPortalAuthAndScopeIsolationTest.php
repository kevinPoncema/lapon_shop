<?php

use App\Models\PartnerStore;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('tenant-auth');

    $this->tenantA = Tenant::factory()->create([
        'name' => 'Comercio Alpha',
        'slug' => 'comercio-alpha',
        'is_active' => true,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'name' => 'Comercio Beta',
        'slug' => 'comercio-beta',
        'is_active' => true,
    ]);

    $this->partnerA = PartnerStore::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Cafetería Delicia',
        'portal_email' => 'caja@delicia.hn',
        'portal_password' => Hash::make('ClaveAliado2026!'),
        'benefit_title' => '25% en repostería artesanal',
        'is_active' => true,
    ]);

    $this->adminA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'ADMIN',
        'is_active' => true,
    ]);
});

test('comercio aliado activo inicia sesion con portal_email y portal_password obteniendo token con ability partner-portal', function () {
    $payload = [
        'email' => 'caja@delicia.hn',
        'password' => 'ClaveAliado2026!',
        'device_name' => 'POS Caja Central',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'comercio-alpha')
        ->postJson('/api/v1/partner-portal/auth/login', $payload);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'abilities',
            'expires_at',
            'partner_store' => ['id', 'name', 'portal_email', 'benefit_title'],
            'origin_tenant' => ['name', 'slug'],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('abilities', ['partner-portal'])
        ->assertJsonPath('partner_store.portal_email', 'caja@delicia.hn')
        ->assertJsonPath('origin_tenant.slug', 'comercio-alpha');

    expect($response->json('access_token'))->not->toBeEmpty();
});

test('comercio aliado consulta su perfil en partner-portal/auth/me y cierra sesion revocando su token', function () {
    $tokenResult = $this->partnerA->createToken('caja-tablet', ['partner-portal']);

    $responseMe = $this->withHeaders([
        'X-Tenant-Slug' => 'comercio-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/partner-portal/auth/me');

    $responseMe->assertOk()
        ->assertJsonPath('partner_store.id', $this->partnerA->id)
        ->assertJsonPath('partner_store.name', 'Cafetería Delicia')
        ->assertJsonPath('origin_tenant.slug', 'comercio-alpha');

    $responseLogout = $this->withHeaders([
        'X-Tenant-Slug' => 'comercio-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->postJson('/api/v1/partner-portal/auth/logout');

    $responseLogout->assertOk()
        ->assertJsonPath('message', 'Sesión cerrada exitosamente en este dispositivo.');

    expect($this->partnerA->fresh()->tokens()->count())->toBe(0);
});

test('BLOQUEO DE ESCALACION VERTICAL: token valido de PartnerStore usado en cualquier ruta /api/v1/admin/* del MISMO tenant recibe 403 Forbidden', function () {
    $tokenResult = $this->partnerA->createToken('caja-pos', ['partner-portal']);

    // 1. Intento hacia /api/v1/admin/auth/me
    $resAuthMe = $this->withHeaders([
        'X-Tenant-Slug' => 'comercio-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/admin/auth/me');

    $resAuthMe->assertStatus(403);

    // 2. Intento hacia /api/v1/admin/partner-stores
    $resPartnerList = $this->withHeaders([
        'X-Tenant-Slug' => 'comercio-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/admin/partner-stores');

    $resPartnerList->assertStatus(403);
});

test('AISLAMIENTO INVERSO: token valido de User Admin usado en rutas /api/v1/partner-portal/* del mismo tenant recibe 403 Forbidden', function () {
    $tokenResult = $this->adminA->createToken('admin-laptop', ['tenant:admin']);

    $response = $this->withHeaders([
        'X-Tenant-Slug' => 'comercio-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/partner-portal/auth/me');

    $response->assertStatus(403);
});

test('comercio aliado con is_active false recibe 403 al iniciar sesion o al usar un token emitido previamente', function () {
    $tokenResult = $this->partnerA->createToken('pos-1', ['partner-portal']);

    $this->partnerA->update(['is_active' => false]);

    // 1. Token previo bloqueado por middleware al estar inactivo
    $responseAccess = $this->withHeaders([
        'X-Tenant-Slug' => 'comercio-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/partner-portal/auth/me');

    $responseAccess->assertStatus(403);

    // 2. Login bloqueado por orquestador y purga remanente
    $responseLogin = $this->withHeader('X-Tenant-Slug', 'comercio-alpha')
        ->postJson('/api/v1/partner-portal/auth/login', [
            'email' => 'caja@delicia.hn',
            'password' => 'ClaveAliado2026!',
        ]);

    $responseLogin->assertStatus(403)
        ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
});

test('token de PartnerStore del Tenant A es rechazado con 403 al enviarse con X-Tenant-Slug del Tenant B', function () {
    $tokenResult = $this->partnerA->createToken('pos-a', ['partner-portal']);

    $response = $this->withHeaders([
        'X-Tenant-Slug' => 'comercio-beta', // Inquilino B
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/partner-portal/auth/me');

    $response->assertStatus(403);
});

test('login en tenant inactivo aborta en primera linea con 404 TENANT_NOT_FOUND sin evaluar credenciales', function () {
    $this->tenantA->update(['is_active' => false]);

    $response = $this->withHeader('X-Tenant-Slug', 'comercio-alpha')
        ->postJson('/api/v1/partner-portal/auth/login', [
            'email' => 'caja@delicia.hn',
            'password' => 'ClaveAliado2026!',
        ]);

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
});
