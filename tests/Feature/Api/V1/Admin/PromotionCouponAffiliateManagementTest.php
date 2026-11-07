<?php

use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-admin-promo',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);
});

test('administrador crea y lista promociones bajo su tenant', function () {
    Sanctum::actingAs($this->admin);

    $payload = [
        'name' => 'Black Friday',
        'type' => 'AUTOMATIC',
        'discount_type' => 'PERCENTAGE',
        'discount_value' => 20.00,
        'conditions' => ['min_subtotal' => 1000.00],
        'allow_stacking' => false,
        'starts_at' => now()->toISOString(),
        'ends_at' => now()->addDays(5)->toISOString(),
        'is_active' => true,
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-admin-promo')
        ->postJson('/api/v1/admin/promotions', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Black Friday')
        ->assertJsonPath('data.discount_value', '20.00');

    $listResponse = $this->withHeader('X-Tenant-Slug', 'tienda-admin-promo')
        ->getJson('/api/v1/admin/promotions');

    $listResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Black Friday');
});

test('administrador crea y lista afiliados y cupones vinculados', function () {
    Sanctum::actingAs($this->admin);

    $promo = Promotion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'COUPON',
    ]);

    $affiliateResponse = $this->withHeader('X-Tenant-Slug', 'tienda-admin-promo')
        ->postJson('/api/v1/admin/affiliates', [
            'name' => 'Carlos Influencer',
            'email' => 'carlos@influencer.hn',
            'phone' => '+50499887766',
            'commission_type' => 'PERCENTAGE',
            'commission_value' => 10.00,
            'is_active' => true,
        ]);

    $affiliateResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Carlos Influencer');

    $affiliateId = $affiliateResponse->json('data.id');

    $couponResponse = $this->withHeader('X-Tenant-Slug', 'tienda-admin-promo')
        ->postJson('/api/v1/admin/coupons', [
            'promotion_id' => $promo->id,
            'affiliate_id' => $affiliateId,
            'code' => 'carlos10',
            'max_uses' => 100,
            'starts_at' => now()->toISOString(),
            'is_active' => true,
        ]);

    $couponResponse->assertStatus(201)
        ->assertJsonPath('data.code', 'CARLOS10');

    $listCoupons = $this->withHeader('X-Tenant-Slug', 'tienda-admin-promo')
        ->getJson('/api/v1/admin/coupons');

    $listCoupons->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'CARLOS10');
});

test('administrador de tenant A no puede ver recursos de promocion de tenant B', function () {
    $tenantB = Tenant::factory()->create(['slug' => 'tienda-b-promo', 'is_active' => true]);
    Promotion::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Promo Secreta B']);

    Sanctum::actingAs($this->admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-b-promo')
        ->getJson('/api/v1/admin/promotions');

    $response->assertStatus(403);
});
