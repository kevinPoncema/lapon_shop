<?php

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::flush();
});

test('administrador puede configurar las tarifas de envio por zona geografica', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-shipping',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'shipping_zones' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'shipping_zones' => [
            ['code' => 'HN-CR', 'name' => 'Cortés (San Pedro Sula y alrededores)', 'fee' => 90.00],
            ['code' => 'HN-FM', 'name' => 'Francisco Morazán (Tegucigalpa)', 'fee' => 130.00],
            ['code' => 'HN-AT', 'name' => 'Atlántida (La Ceiba)', 'fee' => 150.00],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-shipping')
        ->putJson('/api/v1/admin/config/shipping-zones', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'shipping_zones' => [
                ['code' => 'HN-CR', 'fee' => 90.00],
                ['code' => 'HN-FM', 'fee' => 130.00],
                ['code' => 'HN-AT', 'fee' => 150.00],
            ],
        ]);

    $saved = TenantConfig::where('tenant_id', $tenant->id)->first();
    expect($saved->shipping_zones)->toHaveCount(3);
});

test('permite configurar tarifa 0 para envio gratuito', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-free-shipping',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'shipping_zones' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'shipping_zones' => [
            ['code' => 'HN-LOCAL', 'name' => 'Retiro en Tienda', 'fee' => 0.00],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-free-shipping')
        ->putJson('/api/v1/admin/config/shipping-zones', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'shipping_zones' => [
                ['code' => 'HN-LOCAL', 'fee' => 0.00],
            ],
        ]);

    $saved = TenantConfig::where('tenant_id', $tenant->id)->first();
    expect($saved->shipping_zones[0]['fee'])->toBe(0);
});

test('actualizacion de zonas de envio invalida la cache de vitrina', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-shipping-cache',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'shipping_zones' => [],
    ]);

    $cacheKey = "tenant:{$tenant->id}:storefront_config";
    Cache::put($cacheKey, ['cached' => 'shipping'], 3600);

    expect(Cache::has($cacheKey))->toBeTrue();

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-shipping-cache')
        ->putJson('/api/v1/admin/config/shipping-zones', [
            'shipping_zones' => [
                ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 80.00],
            ],
        ]);

    $response->assertOk();
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('rechaza con 422 tarifas con montos negativos', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-negative-fee',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'shipping_zones' => [],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-negative-fee')
        ->putJson('/api/v1/admin/config/shipping-zones', [
            'shipping_zones' => [
                ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => -10.00],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_zones.0.fee']);
});

test('rechaza con 422 elementos de zona que carezcan de codigo o nombre', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-missing-zone-fields',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'shipping_zones' => [],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-missing-zone-fields')
        ->putJson('/api/v1/admin/config/shipping-zones', [
            'shipping_zones' => [
                ['code' => '', 'name' => 'Cortés', 'fee' => 80.00],
                ['code' => 'HN-FM', 'name' => '', 'fee' => 120.00],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_zones.0.code', 'shipping_zones.1.name']);
});

test('rechaza con 422 zonas con codigos duplicados', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-duplicate-zone-code',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'shipping_zones' => [],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-duplicate-zone-code')
        ->putJson('/api/v1/admin/config/shipping-zones', [
            'shipping_zones' => [
                ['code' => 'HN-CR', 'name' => 'Cortés 1', 'fee' => 80.00],
                ['code' => 'hn-cr', 'name' => 'Cortés 2', 'fee' => 90.00],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_zones.0.code']);
});

test('administrador del Tenant A no puede actualizar zonas de envio del Tenant B', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a-ship', 'is_active' => true]);
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'OWNER', 'is_active' => true]);

    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b-ship', 'is_active' => true]);
    TenantConfig::create([
        'tenant_id' => $tenantB->id,
        'shipping_zones' => [
            ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 50.00],
        ],
    ]);

    Sanctum::actingAs($adminA);

    $response = $this->withHeader('X-Tenant-Slug', 'tenant-b-ship')
        ->putJson('/api/v1/admin/config/shipping-zones', [
            'shipping_zones' => [
                ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 999.00],
            ],
        ]);

    $response->assertStatus(403);

    $savedB = TenantConfig::where('tenant_id', $tenantB->id)->first();
    expect((float) $savedB->shipping_zones[0]['fee'])->toBe(50.0);
});
