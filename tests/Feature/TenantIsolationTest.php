<?php

use App\Context\TenantContext;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

test('tenant context scopes all queries to active tenant', function () {
    $context = app(TenantContext::class);

    $tenantA = Tenant::create([
        'name' => 'Tienda A',
        'slug' => 'tienda-a',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Tienda B',
        'slug' => 'tienda-b',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    // Create products for Tenant A and Tenant B
    $productA = Product::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Producto A',
        'slug' => 'producto-a',
        'base_price' => 100.00,
    ]);

    $productB = Product::create([
        'tenant_id' => $tenantB->id,
        'name' => 'Producto B',
        'slug' => 'producto-b',
        'base_price' => 200.00,
    ]);

    // Activate Tenant A
    $context->set($tenantA);

    $productsSeenByA = Product::all();
    expect($productsSeenByA)->toHaveCount(1)
        ->and($productsSeenByA->first()->id)->toBe($productA->id)
        ->and(Product::find($productB->id))->toBeNull();

    // Auto-inject tenant_id on creation
    $categoryA = Category::create([
        'name' => 'Calzado A',
        'slug' => 'calzado-a',
    ]);
    expect($categoryA->tenant_id)->toBe($tenantA->id);

    // Cross-tenant injection attempt must throw LogicException
    expect(function () use ($tenantB) {
        Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Intento Hacker',
            'slug' => 'intento-hacker',
            'base_price' => 50.00,
        ]);
    })->toThrow(LogicException::class);

    // Switch to Tenant B
    $context->set($tenantB);
    $productsSeenByB = Product::all();
    expect($productsSeenByB)->toHaveCount(1)
        ->and($productsSeenByB->first()->id)->toBe($productB->id)
        ->and(Product::find($productA->id))->toBeNull();

    // ExecuteAs safely executes in context and restores previous
    $context->executeAs($tenantA, function () use ($productA) {
        expect(Product::all()->pluck('id')->all())->toBe([$productA->id]);
    });
    expect($context->id())->toBe($tenantB->id);

    // withoutTenantScope allows querying all across tenants
    $allProducts = Product::withoutTenantScope()->get();
    expect($allProducts->count())->toBeGreaterThanOrEqual(2);

    $context->clear();
});

test('creation without tenant_id and without active context throws LogicException', function () {
    $context = app(TenantContext::class);
    $context->clear();

    expect(function () {
        User::create([
            'name' => 'Usuario Huérfano',
            'email' => 'huerfano@test.com',
            'password' => 'secret123',
            'role' => 'STAFF',
        ]);
    })->toThrow(LogicException::class);
});
