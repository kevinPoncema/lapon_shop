<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\TenantConfig;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-vitrina',
        'is_active' => true,
    ]);

    TenantConfig::create([
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
                'options' => ['Negro', 'Café'],
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
        'name' => 'Calzado Masculino',
        'slug' => 'calzado-masculino',
        'is_active' => true,
    ]);
});

test('filtra productos por atributos dimensionales de variante utilizando indices GIN', function () {
    $productMatch = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Zapato Derby Negro',
        'slug' => 'zapato-derby-negro',
        'base_price' => 1200.00,
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $productMatch->id,
        'sku' => 'DERBY-40-NEG',
        'stock' => 5,
        'metadata' => ['talla' => '40', 'color' => 'Negro'],
        'is_active' => true,
    ]);

    $productOther = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Bota Café',
        'slug' => 'bota-cafe',
        'base_price' => 1500.00,
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $productOther->id,
        'sku' => 'BOTA-38-CAFE',
        'stock' => 10,
        'metadata' => ['talla' => '38', 'color' => 'Café'],
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-vitrina')
        ->getJson('/api/v1/storefront/products?attr[talla]=40');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $productMatch->id)
        ->assertJsonPath('data.0.matched_variants.0.sku', 'DERBY-40-NEG');
});

test('omite productos cuyas variantes coincidentes tengan stock cero cuando in_stock es true', function () {
    $productOutOfStock = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zapato Agotado',
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $productOutOfStock->id,
        'sku' => 'AGO-40-NEG',
        'stock' => 0,
        'metadata' => ['talla' => '40', 'color' => 'Negro'],
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-vitrina')
        ->getJson('/api/v1/storefront/products?attr[talla]=40&in_stock=true');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('filtra combinando texto libre rango de precios y metadatos en una sola consulta', function () {
    $productExpected = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Oxford Clásico Cuero',
        'description' => 'Elegante calzado para ocasiones formales',
        'base_price' => 1350.00,
        'metadata' => ['material' => 'Cuero Genuino'],
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $productExpected->id,
        'sku' => 'OXF-40-NEG',
        'stock' => 8,
        'metadata' => ['talla' => '40', 'color' => 'Negro'],
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-vitrina')
        ->getJson('/api/v1/storefront/products?q=oxford&price_min=1000&price_max=1500&attr[color]=Negro&attr[material]=Cuero%20Genuino');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $productExpected->id);
});

test('entrega arbol publico de categorias con productos activos', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'stock' => 5,
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-vitrina')
        ->getJson('/api/v1/storefront/categories');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});

test('entrega detalle publico de producto por slug con variantes activas', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Zapato Monje',
        'slug' => 'zapato-monje',
        'base_price' => 1400.00,
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'sku' => 'MONJE-40',
        'stock' => 4,
        'metadata' => ['talla' => '40', 'color' => 'Negro'],
        'is_default' => true,
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-vitrina')
        ->getJson('/api/v1/storefront/products/zapato-monje');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'zapato-monje')
        ->assertJsonPath('data.variants.0.sku', 'MONJE-40');
});
