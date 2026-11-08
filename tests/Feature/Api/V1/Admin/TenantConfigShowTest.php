<?php

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('administrador autenticado puede obtener la configuracion completa de su tenant', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Tienda Admin',
        'slug' => 'tienda-admin',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $config = TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [
            'primary_color' => '#1E293B',
            'secondary_color' => '#475569',
            'accent_color' => '#F59E0B',
            'font_family' => 'Inter',
        ],
        'contact_info' => [
            'whatsapp_sales' => '+50499887766',
            'support_email' => 'soporte@mitienda.com',
        ],
        'shipping_zones' => [
            ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 80.00],
        ],
        'metadata_schema' => [
            [
                'key' => 'talla',
                'label' => 'Talla',
                'data_type' => 'select',
                'options' => ['38', '39', '40'],
                'is_required' => true,
                'affects_stock' => true,
            ],
        ],
        'filter_config' => [
            ['type' => 'system', 'key' => 'price', 'label' => 'Precio', 'order' => 1],
            ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 2],
        ],
        'outbound_reward_rules' => [
            'min_order_amount' => 1000.00,
            'is_enabled' => true,
        ],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-admin')
        ->getJson('/api/v1/admin/config');

    $response->assertStatus(200)
        ->assertJson([
            'tenant_id' => $tenant->id,
            'branding' => [
                'primary_color' => '#1E293B',
            ],
            'contact_info' => [
                'support_email' => 'soporte@mitienda.com',
            ],
            'shipping_zones' => [
                ['code' => 'HN-CR', 'fee' => 80.00],
            ],
            'metadata_schema' => [
                ['key' => 'talla', 'data_type' => 'select'],
            ],
            'filter_config' => [
                ['key' => 'price'],
                ['key' => 'talla'],
            ],
            'outbound_reward_rules' => [
                'min_order_amount' => 1000.00,
            ],
        ]);
});

test('usuario no autenticado recibe 401 Unauthorized', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-sin-auth',
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-sin-auth')
        ->getJson('/api/v1/admin/config');

    $response->assertStatus(401);
});

test('administrador del Tenant A no puede consultar la configuracion del Tenant B', function () {
    $tenantA = Tenant::factory()->create([
        'name' => 'Tenant A',
        'slug' => 'tenant-a',
        'is_active' => true,
    ]);

    $adminA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $tenantB = Tenant::factory()->create([
        'name' => 'Tenant B',
        'slug' => 'tenant-b',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenantB->id,
        'branding' => ['primary_color' => '#B00000'],
        'contact_info' => ['support_email' => 'secreto_b@tenant-b.com'],
        'shipping_zones' => [],
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    Sanctum::actingAs($adminA);

    // El admin de A intenta consultar la configuración de B con la cabecera de B
    $response = $this->withHeader('X-Tenant-Slug', 'tenant-b')
        ->getJson('/api/v1/admin/config');

    $response->assertStatus(403);
    $response->assertJsonMissing(['support_email' => 'secreto_b@tenant-b.com']);
});
