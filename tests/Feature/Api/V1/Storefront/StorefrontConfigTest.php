<?php

use App\Models\Tenant;
use App\Models\TenantConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Cache::flush();
});

test('retorna 200 con estructura publica completa para un tenant valido', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Calzados Elegantes',
        'slug' => 'calzados-elegantes',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [
            'primary_color' => '#1E293B',
            'secondary_color' => '#475569',
            'accent_color' => '#F59E0B',
            'font_family' => 'Inter',
            'logo_url' => 'https://cdn.plataforma.com/t1/logo.webp',
            'banner_url' => 'https://cdn.plataforma.com/t1/banner.webp',
            'favicon_url' => 'https://cdn.plataforma.com/t1/favicon.ico',
            'social_links' => [
                'whatsapp' => '+50499887766',
                'instagram' => 'calzados_hn',
                'facebook' => 'calzadoseleganteshn',
            ],
        ],
        'contact_info' => [
            'whatsapp_sales' => '+50499887766',
            'support_email' => 'admin_privado@calzados.hn',
        ],
        'metadata_schema' => [
            [
                'key' => 'talla',
                'label' => 'Talla',
                'data_type' => 'select',
                'options' => ['38', '39', '40', '41'],
                'is_required' => true,
                'affects_stock' => true,
            ],
        ],
        'filter_config' => [
            ['type' => 'system', 'key' => 'price', 'label' => 'Rango de Precio', 'data_type' => 'range', 'order' => 1],
            ['type' => 'system', 'key' => 'category', 'label' => 'Categoría', 'data_type' => 'select', 'order' => 2],
            ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 3],
        ],
        'shipping_zones' => [
            ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 80.00],
            ['code' => 'HN-FM', 'name' => 'Francisco Morazán', 'fee' => 120.00],
        ],
        'outbound_reward_rules' => [
            'min_order_amount' => 1000.00,
            'is_enabled' => true,
        ],
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'calzados-elegantes')
        ->getJson('/api/v1/storefront/config');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'tenant' => ['name', 'currency_code', 'country_code'],
            'branding' => [
                'primary_color',
                'secondary_color',
                'accent_color',
                'font_family',
                'logo_url',
                'banner_url',
                'favicon_url',
                'social_links' => ['whatsapp', 'instagram', 'facebook'],
            ],
            'filters' => [
                '*' => ['type', 'key', 'label', 'data_type', 'order'],
            ],
            'shipping_zones' => [
                '*' => ['code', 'name', 'fee'],
            ],
        ]);

    $data = $response->json();
    expect($data['tenant']['name'])->toBe('Calzados Elegantes')
        ->and($data['branding']['primary_color'])->toBe('#1E293B')
        ->and($data['filters'])->toHaveCount(3)
        ->and($data['filters'][2]['affects_stock'])->toBeTrue()
        ->and($data['filters'][2]['options'])->toBe(['38', '39', '40', '41'])
        ->and($data['shipping_zones'])->toHaveCount(2);
});

test('sirve la configuracion de vitrina desde cache de redis en subsecuentes peticiones', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-cache-storefront',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => ['primary_color' => '#000000'],
        'shipping_zones' => [],
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    // 1. Primera petición: carga desde base de datos y almacena en caché
    $this->withHeader('X-Tenant-Slug', 'tienda-cache-storefront')
        ->getJson('/api/v1/storefront/config')
        ->assertOk();

    expect(Cache::has("tenant:{$tenant->id}:storefront_config"))->toBeTrue();

    // 2. Segunda petición: debe ser servida de caché sin consultas a tenant_configs
    DB::enableQueryLog();

    $this->withHeader('X-Tenant-Slug', 'tienda-cache-storefront')
        ->getJson('/api/v1/storefront/config')
        ->assertOk();

    $queries = collect(DB::getQueryLog())->filter(
        fn (array $queryLogRecord) => str_contains((string) $queryLogRecord['query'], 'tenant_configs')
    );

    expect($queries)->toBeEmpty();
});

test('aborta con 404 cuando la peticion no incluye la cabecera X-Tenant-Slug', function () {
    $response = $this->getJson('/api/v1/storefront/config');

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
            ],
        ]);
});

test('aborta con 404 cuando el inquilino solicitado no existe o esta suspendido', function () {
    // Inexistente
    $this->withHeader('X-Tenant-Slug', 'inexistente')
        ->getJson('/api/v1/storefront/config')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'TENANT_NOT_FOUND']]);

    // Suspendido (is_active = false)
    $suspended = Tenant::factory()->create([
        'slug' => 'tienda-suspendida',
        'is_active' => false,
    ]);

    $this->withHeader('X-Tenant-Slug', $suspended->slug)
        ->getJson('/api/v1/storefront/config')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'TENANT_NOT_FOUND']]);
});

test('no expone campos sensibles como outbound_reward_rules en la respuesta publica', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-segura',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => ['primary_color' => '#123456'],
        'contact_info' => [
            'whatsapp_sales' => '+50499887766',
            'support_email' => 'admin@tienda.com',
            'bank_account' => 'BAC 1234567890',
        ],
        'outbound_reward_rules' => [
            'min_order_amount' => 5000.00,
            'reward_partner_id' => '110e8400-e29b-41d4-a716-446655440001',
            'is_enabled' => true,
        ],
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-segura')
        ->getJson('/api/v1/storefront/config');

    $response->assertOk();
    $response->assertJsonMissingPath('outbound_reward_rules');
    $response->assertJsonMissingPath('contact_info.bank_account');
    $response->assertJsonMissingPath('contact_info.support_email');
});

test('retorna filtros y zonas vacias cuando el tenant no ha configurado valores aun', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Tienda Nueva',
        'slug' => 'tienda-nueva',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [],
        'shipping_zones' => [],
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-nueva')
        ->getJson('/api/v1/storefront/config');

    $response->assertOk()
        ->assertJson([
            'tenant' => [
                'name' => 'Tienda Nueva',
            ],
            'filters' => [],
            'shipping_zones' => [],
        ]);
});
