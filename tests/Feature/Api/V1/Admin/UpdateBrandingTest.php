<?php

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::flush();
});

test('administrador puede actualizar la paleta de colores y enlaces sociales', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-branding',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [],
    ]);

    Sanctum::actingAs($admin);

    $payload = [
        'primary_color' => '#0F172A',
        'secondary_color' => '#334155',
        'accent_color' => '#E11D48',
        'font_family' => 'Plus Jakarta Sans',
        'logo_url' => 'https://cdn.plataforma.com/t1/nuevo-logo.webp',
        'banner_url' => 'https://cdn.plataforma.com/t1/nuevo-banner.webp',
        'favicon_url' => 'https://cdn.plataforma.com/t1/favicon.ico',
        'social_links' => [
            'whatsapp' => '+50499001122',
            'instagram' => 'nueva_tienda_hn',
        ],
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-branding')
        ->putJson('/api/v1/admin/config/branding', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'branding' => [
                'primary_color' => '#0F172A',
                'secondary_color' => '#334155',
                'accent_color' => '#E11D48',
                'font_family' => 'Plus Jakarta Sans',
                'logo_url' => 'https://cdn.plataforma.com/t1/nuevo-logo.webp',
                'social_links' => [
                    'whatsapp' => '+50499001122',
                    'instagram' => 'nueva_tienda_hn',
                ],
            ],
        ]);

    $saved = TenantConfig::where('tenant_id', $tenant->id)->first();
    expect($saved->branding['primary_color'])->toBe('#0F172A')
        ->and($saved->branding['font_family'])->toBe('Plus Jakarta Sans');
});

test('actualizacion de branding invalida la cache de storefront', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-branding-cache',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [],
    ]);

    $cacheKey = "tenant:{$tenant->id}:storefront_config";
    Cache::put($cacheKey, ['cached' => 'branding'], 3600);

    expect(Cache::has($cacheKey))->toBeTrue();

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-branding-cache')
        ->putJson('/api/v1/admin/config/branding', [
            'primary_color' => '#123456',
        ]);

    $response->assertOk();
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('rechaza con 422 colores que no cumplan formato hexadecimal valido', function (string $invalidColor) {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-invalid-colors',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-invalid-colors')
        ->putJson('/api/v1/admin/config/branding', [
            'primary_color' => $invalidColor,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['primary_color']);
})->with([
    '#FFF',
    '#FFF1',
    'rojo',
    '#ZZZZZZ',
    '123456',
    '#12345G',
]);

test('rechaza URLs malformadas para logotipo o banner', function (string $invalidUrl) {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-invalid-urls',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-invalid-urls')
        ->putJson('/api/v1/admin/config/branding', [
            'logo_url' => $invalidUrl,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['logo_url']);
})->with([
    'ftp://sitio.com/logo.png',
    'http://sitio.com/logo.png', // Debe ser https
    'no-es-una-url',
    'javascript:alert(1)',
]);

test('acepta colores en minusculas o mayusculas normalizandolos', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-normalize-colors',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'OWNER',
        'is_active' => true,
    ]);

    TenantConfig::create([
        'tenant_id' => $tenant->id,
        'branding' => [],
    ]);

    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-normalize-colors')
        ->putJson('/api/v1/admin/config/branding', [
            'primary_color' => '#0f172a',
            'secondary_color' => '#334155',
            'accent_color' => '#e11d48',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'branding' => [
                'primary_color' => '#0F172A',
                'secondary_color' => '#334155',
                'accent_color' => '#E11D48',
            ],
        ]);

    $saved = TenantConfig::where('tenant_id', $tenant->id)->first();
    expect($saved->branding['primary_color'])->toBe('#0F172A');
});

test('administrador del Tenant A no puede actualizar el branding del Tenant B', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'tenant-a-brand', 'is_active' => true]);
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'OWNER', 'is_active' => true]);

    $tenantB = Tenant::factory()->create(['slug' => 'tenant-b-brand', 'is_active' => true]);
    TenantConfig::create([
        'tenant_id' => $tenantB->id,
        'branding' => ['primary_color' => '#111111'],
    ]);

    Sanctum::actingAs($adminA);

    $response = $this->withHeader('X-Tenant-Slug', 'tenant-b-brand')
        ->putJson('/api/v1/admin/config/branding', [
            'primary_color' => '#999999',
        ]);

    $response->assertStatus(403);

    $savedB = TenantConfig::where('tenant_id', $tenantB->id)->first();
    expect($savedB->branding['primary_color'])->toBe('#111111');
});
