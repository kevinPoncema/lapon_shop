<?php

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::flush();
});

test('administrador puede actualizar exitosamente metadata_schema y filter_config', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-schema',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'metadata_schema' => [
            [
                'key' => 'talla',
                'label' => 'Talla Calzado',
                'data_type' => 'select',
                'options' => ['38', '39', '40', '41', '42'],
                'is_required' => true,
                'affects_stock' => true,
            ],
            [
                'key' => 'material',
                'label' => 'Material Exterior',
                'data_type' => 'text',
                'is_required' => false,
                'affects_stock' => false,
            ],
        ],
        'filter_config' => [
            ['type' => 'system', 'key' => 'price', 'label' => 'Rango de Precio', 'order' => 1],
            ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 2],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-schema')
        ->putJson('/api/v1/admin/config/catalog-schema', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'metadata_schema' => [
                ['key' => 'talla', 'data_type' => 'select', 'affects_stock' => true],
                ['key' => 'material', 'data_type' => 'text', 'affects_stock' => false],
            ],
            'filter_config' => [
                ['key' => 'price', 'type' => 'system'],
                ['key' => 'talla', 'type' => 'metadata'],
            ],
        ]);

    $updated = TenantConfig::where('tenant_id', $tenant->id)->first();
    expect($updated->metadata_schema)->toHaveCount(2)
        ->and($updated->filter_config)->toHaveCount(2);
});

test('actualizar el catalogo schema invalida la cache de vitrina en Redis', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-cache-schema',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    $cacheKey = "tenant:{$tenant->id}:storefront_config";
    Cache::put($cacheKey, ['old' => 'data'], 3600);

    expect(Cache::has($cacheKey))->toBeTrue();

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-cache-schema')
        ->putJson('/api/v1/admin/config/catalog-schema', [
            'metadata_schema' => [],
            'filter_config' => [],
        ]);

    $response->assertOk();
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('purga automaticamente filtros huerfanos presentes en filter_config', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-orphan-purge',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'metadata_schema' => [
            [
                'key' => 'talla',
                'label' => 'Talla',
                'data_type' => 'select',
                'options' => ['38', '39'],
                'is_required' => true,
                'affects_stock' => true,
            ],
        ],
        'filter_config' => [
            ['type' => 'system', 'key' => 'price', 'label' => 'Precio', 'order' => 1],
            ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 2],
            ['type' => 'metadata', 'key' => 'color_fantasma', 'label' => 'Color Fantasma', 'order' => 3],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-orphan-purge')
        ->putJson('/api/v1/admin/config/catalog-schema', $payload);

    $response->assertStatus(200);

    $savedConfig = TenantConfig::where('tenant_id', $tenant->id)->first();
    $filterKeys = collect($savedConfig->filter_config)->pluck('key')->all();

    expect($filterKeys)->toBe(['price', 'talla'])
        ->and($filterKeys)->not->toContain('color_fantasma');
});

test('rechaza con 422 el uso de palabras reservadas del sistema como claves de metadato', function (string $reservedKey) {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-reserved-words',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'metadata_schema' => [
            [
                'key' => $reservedKey,
                'label' => 'Campo Reservado',
                'data_type' => 'text',
                'is_required' => false,
                'affects_stock' => false,
            ],
        ],
        'filter_config' => [],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-reserved-words')
        ->putJson('/api/v1/admin/config/catalog-schema', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['metadata_schema.0.key']);

    $errors = $response->json('errors');
    expect($errors['metadata_schema.0.key'][0])
        ->toBe("La clave {$reservedKey} es una palabra reservada del sistema y no puede ser utilizada como metadato dinámico.");
})->with([
    'id',
    'sku',
    'price',
    'stock',
    'status',
    'is_active',
    'metadata',
]);

test('rechaza con 422 atributos tipo select que no proporcionen el arreglo options', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-select-no-options',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'metadata_schema' => [
            [
                'key' => 'talla',
                'label' => 'Talla',
                'data_type' => 'select',
                'options' => [],
                'is_required' => true,
                'affects_stock' => true,
            ],
        ],
        'filter_config' => [],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-select-no-options')
        ->putJson('/api/v1/admin/config/catalog-schema', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['metadata_schema.0.options']);
});

test('rechaza con 422 claves con caracteres especiales o espacios no conformes con snake_case', function (string $invalidKey) {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-invalid-keys',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => [],
        'filter_config' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'metadata_schema' => [
            [
                'key' => $invalidKey,
                'label' => 'Clave Invalida',
                'data_type' => 'text',
                'is_required' => false,
                'affects_stock' => false,
            ],
        ],
        'filter_config' => [],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-invalid-keys')
        ->putJson('/api/v1/admin/config/catalog-schema', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['metadata_schema.0.key']);
})->with([
    'Talla Zapato',
    'talla-zapato',
    'color#primario',
    '123talla',
    'talla@',
]);

test('permite vaciar completamente el esquema de metadatos enviando arreglos vacios', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-empty-schema',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => [
            ['key' => 'talla', 'label' => 'Talla', 'data_type' => 'text'],
        ],
        'filter_config' => [
            ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 1],
        ],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-empty-schema')
        ->putJson('/api/v1/admin/config/catalog-schema', [
            'metadata_schema' => [],
            'filter_config' => [],
        ]);

    $response->assertStatus(200);

    $saved = TenantConfig::where('tenant_id', $tenant->id)->first();
    expect($saved->metadata_schema)->toBe([])
        ->and($saved->filter_config)->toBe([]);
});

test('preserva atributos existentes cuando solo se reordenan los filtros', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-reorder-filters',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $initialSchema = [
        [
            'key' => 'talla',
            'label' => 'Talla',
            'data_type' => 'select',
            'options' => ['38', '39'],
            'is_required' => true,
            'affects_stock' => true,
        ],
    ];

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'metadata_schema' => $initialSchema,
        'filter_config' => [
            ['type' => 'system', 'key' => 'price', 'label' => 'Precio', 'order' => 1],
            ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 2],
        ],
    ]);

    Sanctum::actingAs($admin);

    // Solo se envían los filtros reordenados
    $response = $this->withHeader('X-Tenant-Slug', 'tienda-reorder-filters')
        ->putJson('/api/v1/admin/config/catalog-schema', [
            'filter_config' => [
                ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 1],
                ['type' => 'system', 'key' => 'price', 'label' => 'Precio', 'order' => 2],
            ],
        ]);

    $response->assertStatus(200);

    $saved = TenantConfig::where('tenant_id', $tenant->id)->first();
    expect($saved->metadata_schema)->toHaveCount(1)
        ->and($saved->metadata_schema[0]['key'])->toBe('talla')
        ->and($saved->filter_config[0]['key'])->toBe('talla')
        ->and($saved->filter_config[1]['key'])->toBe('price');
});

test('administrador del Tenant A no puede actualizar el catalogo schema del Tenant B', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a-schema', 'is_active' => true]);
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'OWNER', 'is_active' => true]);

    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b-schema', 'is_active' => true]);
    $initialConfigB = TenantConfig::create([
        'tenant_id' => $tenantB->id,
        'metadata_schema' => [
            ['key' => 'color', 'label' => 'Color', 'data_type' => 'text'],
        ],
        'filter_config' => [],
    ]);

    Sanctum::actingAs($adminA);

    $response = $this->withHeader('X-Tenant-Slug', 'tenant-b-schema')
        ->putJson('/api/v1/admin/config/catalog-schema', [
            'metadata_schema' => [
                ['key' => 'hack', 'label' => 'Hack', 'data_type' => 'text'],
            ],
            'filter_config' => [],
        ]);

    $response->assertStatus(403);

    $configBAfter = TenantConfig::where('tenant_id', $tenantB->id)->first();
    expect($configBAfter->metadata_schema[0]['key'])->toBe('color');
});
