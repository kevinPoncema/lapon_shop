<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-calzado',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->config = TenantConfig::create([
        'tenant_id' => $this->tenant->id,
        'metadata_schema' => [
            [
                'key' => 'talla',
                'label' => 'Talla',
                'data_type' => 'select',
                'options' => ['38', '39', '40', '41'],
                'is_required' => true,
                'affects_stock' => true,
            ],
            [
                'key' => 'color',
                'label' => 'Color',
                'data_type' => 'select',
                'options' => ['Negro', 'Café', 'Azul'],
                'is_required' => true,
                'affects_stock' => true,
            ],
            [
                'key' => 'material',
                'label' => 'Material',
                'data_type' => 'text',
                'is_required' => false,
                'affects_stock' => false,
            ],
        ],
        'filter_config' => [],
    ]);

    $this->category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Calzado Formal',
        'slug' => 'calzado-formal',
    ]);
});

test('administrador crea exitosamente un producto con multiples variantes gobernadas por esquema', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Zapato Oxford Clásico',
        'description' => 'Calzado de cuero genuino.',
        'base_price' => 1450.00,
        'images' => ['https://cdn.plataforma.com/t1/prod1-front.webp'],
        'metadata' => [
            'material' => 'Cuero Vacuno',
        ],
        'variants' => [
            [
                'sku' => 'OXF-CUERO-39-NEG',
                'price' => null,
                'stock' => 15,
                'metadata' => ['talla' => '39', 'color' => 'Negro'],
                'is_default' => true,
            ],
            [
                'sku' => 'OXF-CUERO-40-NEG',
                'price' => null,
                'stock' => 20,
                'metadata' => ['talla' => '40', 'color' => 'Negro'],
                'is_default' => false,
            ],
            [
                'sku' => 'OXF-CUERO-41-CAFE',
                'price' => 1550.00,
                'stock' => 8,
                'metadata' => ['talla' => '41', 'color' => 'Café'],
                'is_default' => false,
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Zapato Oxford Clásico')
        ->assertJsonPath('data.variants_count', 3)
        ->assertJsonPath('data.total_stock', 43)
        ->assertJsonPath('data.variants.0.sku', 'OXF-CUERO-39-NEG')
        ->assertJsonPath('data.variants.0.price', 1450)
        ->assertJsonPath('data.variants.0.price_override', null)
        ->assertJsonPath('data.variants.2.price', 1550)
        ->assertJsonPath('data.variants.2.price_override', 1550);

    $this->assertDatabaseHas('products', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Zapato Oxford Clásico',
        'slug' => 'zapato-oxford-clasico',
    ]);

    $this->assertDatabaseCount('product_variants', 3);
});

test('autogenera codigos SKU unicos para variantes que no proporcionan SKU explicito', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Mocasín Casual',
        'base_price' => 990.00,
        'variants' => [
            [
                'sku' => null,
                'stock' => 10,
                'metadata' => ['talla' => '39', 'color' => 'Café'],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(201);

    $createdVariant = ProductVariant::where('tenant_id', $this->tenant->id)->first();
    expect($createdVariant->sku)->toBe('MOCASIN-CASUAL-39-CAFE');
});

test('asigna automaticamente la primera variante como default cuando no se especifica is_default', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Botas Trekking',
        'base_price' => 2100.00,
        'variants' => [
            [
                'sku' => 'BOT-39',
                'stock' => 5,
                'metadata' => ['talla' => '39', 'color' => 'Negro'],
                'is_default' => false,
            ],
            [
                'sku' => 'BOT-40',
                'stock' => 12,
                'metadata' => ['talla' => '40', 'color' => 'Negro'],
                'is_default' => false,
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(201);

    $v1 = ProductVariant::where('sku', 'BOT-39')->first();
    $v2 = ProductVariant::where('sku', 'BOT-40')->first();

    expect($v1->is_default)->toBeTrue()
        ->and($v2->is_default)->toBeFalse();
});

test('hereda el base_price del producto cuando variant.price es nulo y respeta sobrescritura cuando tiene valor', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Sandalia Verano',
        'base_price' => 500.00,
        'variants' => [
            [
                'sku' => 'SAN-38',
                'price' => null,
                'stock' => 10,
                'metadata' => ['talla' => '38', 'color' => 'Azul'],
            ],
            [
                'sku' => 'SAN-39',
                'price' => 550.00,
                'stock' => 6,
                'metadata' => ['talla' => '39', 'color' => 'Azul'],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.variants.0.price', 500)
        ->assertJsonPath('data.variants.0.price_override', null)
        ->assertJsonPath('data.variants.1.price', 550)
        ->assertJsonPath('data.variants.1.price_override', 550);
});

test('rechaza con 422 cuando dos variantes dentro del mismo producto tienen la misma combinacion de metadatos', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Zapatilla Duplicada',
        'base_price' => 800.00,
        'variants' => [
            [
                'sku' => 'ZAP-1',
                'stock' => 5,
                'metadata' => ['talla' => '40', 'color' => 'Negro'],
            ],
            [
                'sku' => 'ZAP-2',
                'stock' => 10,
                'metadata' => ['talla' => '40', 'color' => 'Negro'],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['variants']);
});

test('rechaza con 422 cuando una variante incluye atributos con affects_stock false o no registrados en el esquema', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Zapatilla Invalida',
        'base_price' => 800.00,
        'variants' => [
            [
                'sku' => 'ZAP-INV',
                'stock' => 5,
                'metadata' => [
                    'talla' => '40',
                    'color' => 'Negro',
                    'material' => 'Cuero',
                ],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['variants.0.metadata']);
});

test('rechaza con 422 cuando el valor de una opcion select no coincide con las opciones permitidas en el esquema', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Zapatilla Talla Inexistente',
        'base_price' => 800.00,
        'variants' => [
            [
                'sku' => 'ZAP-48',
                'stock' => 5,
                'metadata' => ['talla' => '48', 'color' => 'Negro'],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['variants.0.metadata.talla']);
});

test('rechaza con 422 cuando se intenta registrar un SKU duplicado para el mismo inquilino', function () {
    Sanctum::actingAs($this->admin);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sku' => 'SKU-REPETIDO',
    ]);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Nuevo Producto',
        'base_price' => 500.00,
        'variants' => [
            [
                'sku' => 'SKU-REPETIDO',
                'stock' => 10,
                'metadata' => ['talla' => '40', 'color' => 'Negro'],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['variants.0.sku']);
});

test('permite registrar el mismo SKU en dos inquilinos diferentes garantizando aislamiento multi-tenant', function () {
    $tenantB = Tenant::factory()->create(['slug' => 'tienda-b', 'is_active' => true]);
    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'OWNER', 'is_active' => true]);

    TenantConfig::create([
        'tenant_id' => $tenantB->id,
        'metadata_schema' => [
            ['key' => 'talla', 'label' => 'Talla', 'data_type' => 'select', 'options' => ['40'], 'is_required' => true, 'affects_stock' => true],
        ],
        'filter_config' => [],
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sku' => 'SKU-COMPARTIDO',
    ]);

    Sanctum::actingAs($adminB);

    $payload = [
        'name' => 'Producto Tenant B',
        'base_price' => 700.00,
        'variants' => [
            [
                'sku' => 'SKU-COMPARTIDO',
                'stock' => 10,
                'metadata' => ['talla' => '40'],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-b')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(201);
});

test('rechaza con 422 la creacion de producto con stock de variante negativo', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'category_id' => $this->category->id,
        'name' => 'Producto Stock Negativo',
        'base_price' => 500.00,
        'variants' => [
            [
                'sku' => 'SKU-NEG',
                'stock' => -5,
                'metadata' => ['talla' => '40', 'color' => 'Negro'],
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['variants.0.stock']);
});

test('administrador gestiona categorias mediante endpoints administrativos', function () {
    Sanctum::actingAs($this->admin);

    $storeResponse = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->postJson('/api/v1/admin/categories', [
            'name' => 'Deportes',
            'slug' => 'deportes',
            'is_active' => true,
        ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Deportes')
        ->assertJsonPath('data.slug', 'deportes');

    $listResponse = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson('/api/v1/admin/categories');

    $listResponse->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'children']]]);
});

test('administrador puede consultar producto con variantes y stock total', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Zapato Detalle',
        'slug' => 'zapato-detalle',
        'base_price' => 1200.00,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'sku' => 'VAR-1',
        'stock' => 10,
        'is_default' => true,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'sku' => 'VAR-2',
        'stock' => 15,
        'is_default' => false,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson("/api/v1/admin/products/{$product->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.variants_count', 2)
        ->assertJsonPath('data.total_stock', 25);
});

test('administrador puede actualizar producto y sincronizar variantes', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Zapato Original',
        'base_price' => 1000.00,
    ]);

    $variantExisting = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'sku' => 'ZAP-ORIG-39',
        'stock' => 5,
        'metadata' => ['talla' => '39', 'color' => 'Negro'],
        'is_default' => true,
    ]);

    $payload = [
        'name' => 'Zapato Modificado',
        'base_price' => 1100.00,
        'variants' => [
            [
                'id' => $variantExisting->id,
                'sku' => 'ZAP-ORIG-39',
                'stock' => 8,
                'metadata' => ['talla' => '39', 'color' => 'Negro'],
                'is_default' => true,
            ],
            [
                'sku' => 'ZAP-NUEVO-40',
                'stock' => 12,
                'metadata' => ['talla' => '40', 'color' => 'Negro'],
                'is_default' => false,
            ],
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->putJson("/api/v1/admin/products/{$product->id}", $payload);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Zapato Modificado')
        ->assertJsonPath('data.variants_count', 2)
        ->assertJsonPath('data.total_stock', 20);

    expect($variantExisting->fresh()->stock)->toBe(8);
});

test('desactivar variante por defecto promueve automaticamente otra variante activa como default', function () {
    Sanctum::actingAs($this->admin);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
    ]);

    $defaultVariant = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'sku' => 'VAR-DEF',
        'is_default' => true,
        'is_active' => true,
    ]);

    $secondaryVariant = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'sku' => 'VAR-SEC',
        'is_default' => false,
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->patchJson("/api/v1/admin/variants/{$defaultVariant->id}/status", [
            'is_active' => false,
        ]);

    $response->assertOk();

    expect($defaultVariant->fresh()->is_active)->toBeFalse()
        ->and($defaultVariant->fresh()->is_default)->toBeFalse()
        ->and($secondaryVariant->fresh()->is_default)->toBeTrue();
});

test('administrador de tenant A no puede ver ni modificar productos de tenant B', function () {
    $tenantB = Tenant::factory()->create(['slug' => 'tienda-b', 'is_active' => true]);
    $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);

    Sanctum::actingAs($this->admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-b')
        ->getJson("/api/v1/admin/products/{$productB->id}");

    $response->assertStatus(403);
});

test('administrador puede paginar y filtrar productos por categoria, estado, stock y atributos dinamicos', function () {
    Sanctum::actingAs($this->admin);

    $category2 = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Accesorios Deportivos',
        'slug' => 'accesorios-deportivos',
    ]);

    // Producto 1: en Calzado Formal, activo, con stock, talla 40, color Negro
    $prod1 = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Zapato Oxford Elegante',
        'is_active' => true,
        'metadata' => ['material' => 'Cuero'],
    ]);
    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $prod1->id,
        'sku' => 'OXFORD-40-NEG',
        'stock' => 15,
        'is_active' => true,
        'metadata' => ['talla' => '40', 'color' => 'Negro'],
    ]);

    // Producto 2: en Accesorios, inactivo/oculto, sin stock, talla 38, color Café
    $prod2 = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $category2->id,
        'name' => 'Cinturón Deportivo',
        'is_active' => false,
        'metadata' => ['material' => 'Lona'],
    ]);
    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $prod2->id,
        'sku' => 'CINT-DEP-01',
        'stock' => 0,
        'is_active' => true,
        'metadata' => ['talla' => '38', 'color' => 'Café'],
    ]);

    // 1. Paginación y total
    $resAll = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson('/api/v1/admin/products?per_page=1&page=1')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);
    expect($resAll->json('meta.total'))->toBe(2)
        ->and($resAll->json('meta.per_page'))->toBe(1)
        ->and(count($resAll->json('data')))->toBe(1);

    // 2. Filtro por búsqueda de SKU
    $resSku = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson('/api/v1/admin/products?q=OXFORD-40')
        ->assertOk();
    expect($resSku->json('meta.total'))->toBe(1)
        ->and($resSku->json('data.0.id'))->toBe($prod1->id);

    // 3. Filtro por categoría
    $resCat = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson("/api/v1/admin/products?category_id={$category2->id}")
        ->assertOk();
    expect($resCat->json('meta.total'))->toBe(1)
        ->and($resCat->json('data.0.id'))->toBe($prod2->id);

    // 4. Filtro por estado activo
    $resActive = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson('/api/v1/admin/products?is_active=1')
        ->assertOk();
    expect($resActive->json('meta.total'))->toBe(1)
        ->and($resActive->json('data.0.id'))->toBe($prod1->id);

    // 5. Filtro por stock disponible
    $resStock = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson('/api/v1/admin/products?in_stock=1')
        ->assertOk();
    expect($resStock->json('meta.total'))->toBe(1)
        ->and($resStock->json('data.0.id'))->toBe($prod1->id);

    // 6. Filtro por atributo dinámico en variante (talla=40)
    $resAttr = $this->withHeader('X-Tenant-Slug', 'tienda-calzado')
        ->getJson('/api/v1/admin/products?attr[talla]=40')
        ->assertOk();
    expect($resAttr->json('meta.total'))->toBe(1)
        ->and($resAttr->json('data.0.id'))->toBe($prod1->id);
});
