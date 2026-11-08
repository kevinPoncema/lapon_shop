<?php

use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

test('renders multi-tenant welcome launcher on base host with active tenants only', function () {
    $activeTenant = Tenant::factory()->create([
        'name' => 'Kinetic Wear HN',
        'slug' => 'kinetic-wear',
        'is_active' => true,
    ]);

    Tenant::factory()->create([
        'name' => 'Suspended Shop',
        'slug' => 'suspended-shop',
        'is_active' => false,
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('tenants', 1)
            ->where('tenants.0.slug', $activeTenant->slug)
        );
});

test('resolves storefront, admin, and partner shells via path prefix /t/{tenantSlug}', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Kinetic Wear HN',
        'slug' => 'kinetic-wear',
        'currency_code' => 'HNL',
        'country_code' => 'HN',
        'is_active' => true,
    ]);

    $this->get("/t/{$tenant->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/catalog')
            ->where('tenantSlug', 'kinetic-wear')
            ->where('urlMode', 'path')
        );

    $this->get("/t/{$tenant->slug}/p/chaqueta-pro")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/product-detail')
            ->where('tenantSlug', 'kinetic-wear')
            ->where('productSlug', 'chaqueta-pro')
        );

    $this->get("/t/{$tenant->slug}/checkout")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/checkout')
            ->where('tenantSlug', 'kinetic-wear')
        );

    $this->get("/t/{$tenant->slug}/admin")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant-admin/orders')
            ->where('tenantSlug', 'kinetic-wear')
        );

    $this->get("/t/{$tenant->slug}/partner-portal")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('partner-portal/pos-scanner')
            ->where('tenantSlug', 'kinetic-wear')
        );
});

test('returns 404 when accessing path shell of non-existent or inactive tenant', function () {
    Tenant::factory()->create([
        'slug' => 'inactive-store',
        'is_active' => false,
    ]);

    $this->get('/t/missing-store')->assertNotFound();
    $this->get('/t/inactive-store')->assertNotFound();
});

test('resolves storefront and admin shells automatically via tenant subdomain', function () {
    Tenant::factory()->create([
        'name' => 'Kinetic Wear HN',
        'slug' => 'kinetic-wear',
        'is_active' => true,
    ]);

    $this->get('http://kinetic-wear.commercehub.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/catalog')
            ->where('tenantSlug', 'kinetic-wear')
            ->where('urlMode', 'subdomain')
        );

    $this->get('http://kinetic-wear.commercehub.test/checkout')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/checkout')
            ->where('tenantSlug', 'kinetic-wear')
            ->where('urlMode', 'subdomain')
        );

    $this->get('http://kinetic-wear.commercehub.test/admin/schema')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant-admin/schema-settings')
            ->where('tenantSlug', 'kinetic-wear')
            ->where('urlMode', 'subdomain')
        );
});
