<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-inventario',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zapato Derby',
    ]);

    $this->variant = ProductVariant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'sku' => 'DERBY-40-NEG',
        'stock' => 15,
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('ajusta exitosamente el stock en modo set reemplazando el valor absoluto', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-inventario')
        ->patchJson("/api/v1/admin/variants/{$this->variant->id}/stock", [
            'mode' => 'set',
            'quantity' => 25,
            'reason' => 'Ingreso de nuevo lote',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.variant_id', $this->variant->id)
        ->assertJsonPath('data.sku', 'DERBY-40-NEG')
        ->assertJsonPath('data.previous_stock', 15)
        ->assertJsonPath('data.current_stock', 25)
        ->assertJsonPath('data.adjusted_by', 10);

    expect($this->variant->fresh()->stock)->toBe(25);
});

test('ajusta exitosamente el stock en modo adjust incrementando o decrementando de forma relativa', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-inventario')
        ->patchJson("/api/v1/admin/variants/{$this->variant->id}/stock", [
            'mode' => 'adjust',
            'quantity' => -5,
            'reason' => 'Venta presencial en showroom',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.variant_id', $this->variant->id)
        ->assertJsonPath('data.previous_stock', 15)
        ->assertJsonPath('data.current_stock', 10)
        ->assertJsonPath('data.adjusted_by', -5);

    expect($this->variant->fresh()->stock)->toBe(10);
});

test('rechaza con 422 ajuste relativo que resulte en stock negativo', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-inventario')
        ->patchJson("/api/v1/admin/variants/{$this->variant->id}/stock", [
            'mode' => 'adjust',
            'quantity' => -20,
            'reason' => 'Ajuste excesivo',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'INSUFFICIENT_STOCK_FOR_ADJUSTMENT',
                'message' => 'No se puede decrementar 20 unidades. El stock actual es de 15 unidades.',
            ],
        ]);

    expect($this->variant->fresh()->stock)->toBe(15);
});

test('administrador de tenant A no puede ajustar stock de variante de tenant B', function () {
    $tenantB = Tenant::factory()->create(['slug' => 'tienda-b-inv', 'is_active' => true]);
    $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);
    $variantB = ProductVariant::factory()->create([
        'tenant_id' => $tenantB->id,
        'product_id' => $productB->id,
        'stock' => 10,
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-b-inv')
        ->patchJson("/api/v1/admin/variants/{$variantB->id}/stock", [
            'mode' => 'set',
            'quantity' => 50,
        ]);

    $response->assertStatus(403);
    expect($variantB->fresh()->stock)->toBe(10);
});
