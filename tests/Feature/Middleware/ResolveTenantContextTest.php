<?php

use App\Context\TenantContext;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('tenant.context')->get('/test-tenant-resolution', function () {
        $context = app(TenantContext::class);

        return response()->json([
            'status' => 'success',
            'tenant_id' => $context->id(),
            'tenant_slug' => $context->get()->slug,
            'request_tenant_id' => request()->attributes->get('tenant')?->id,
        ]);
    });

    Route::middleware('tenant.context')->get('/test-tenant-scope-activation', function () {
        return response()->json([
            'categories' => Category::all()->pluck('name')->all(),
        ]);
    });
});

test('aborts with 404 when neither tenant header nor host subdomain is provided', function () {
    $response = $this->getJson('/test-tenant-resolution');

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'El inquilino solicitado no existe o se encuentra inactivo.',
            ],
        ]);
});

test('aborts with 404 when slug does not exist in database', function () {
    $response = $this->withHeader('X-Tenant-Slug', 'empresa-inexistente')
        ->getJson('/test-tenant-resolution');

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'El inquilino solicitado no existe o se encuentra inactivo.',
            ],
        ]);
});

test('aborts with 404 when tenant exists but is inactive', function () {
    $inactiveTenant = Tenant::factory()->create([
        'slug' => 'tienda-suspendida',
        'is_active' => false,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', $inactiveTenant->slug)
        ->getJson('/test-tenant-resolution');

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'El inquilino solicitado no existe o se encuentra inactivo.',
            ],
        ]);
});

test('resolves tenant and populates context and request attribute with valid header', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-valida',
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-valida')
        ->getJson('/test-tenant-resolution');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'tenant_id' => $tenant->id,
            'tenant_slug' => 'tienda-valida',
            'request_tenant_id' => $tenant->id,
        ]);

    expect(app(TenantContext::class)->isSet())->toBeTrue()
        ->and(app(TenantContext::class)->id())->toBe($tenant->id);
});

test('resolves tenant and normalizes uppercase and trimmed header slug', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-normalizada',
        'is_active' => true,
    ]);

    $response = $this->withHeader('X-Tenant-Slug', '  TIENDA-NORMALIZADA  ')
        ->getJson('/test-tenant-resolution');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'tenant_id' => $tenant->id,
            'tenant_slug' => 'tienda-normalizada',
            'request_tenant_id' => $tenant->id,
        ]);
});

test('resolves tenant through host subdomain fallback', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-subdominio',
        'is_active' => true,
    ]);

    $response = $this->withServerVariables(['HTTP_HOST' => 'tienda-subdominio.plataforma.test'])
        ->getJson('/test-tenant-resolution');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'tenant_id' => $tenant->id,
            'tenant_slug' => 'tienda-subdominio',
            'request_tenant_id' => $tenant->id,
        ]);
});

test('ignores reserved host subdomains such as www and api during fallback resolution', function () {
    $response = $this->withServerVariables(['HTTP_HOST' => 'www.plataforma.test'])
        ->getJson('/test-tenant-resolution');

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'El inquilino solicitado no existe o se encuentra inactivo.',
            ],
        ]);

    $responseApi = $this->withServerVariables(['HTTP_HOST' => 'api.plataforma.test'])
        ->getJson('/test-tenant-resolution');

    $responseApi->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'El inquilino solicitado no existe o se encuentra inactivo.',
            ],
        ]);
});

test('caches tenant resolution avoiding redundant database queries', function () {
    Cache::flush();

    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-con-cache',
        'is_active' => true,
    ]);

    $this->withHeader('X-Tenant-Slug', 'tienda-con-cache')
        ->getJson('/test-tenant-resolution')
        ->assertOk();

    expect(Cache::has("tenant:slug:{$tenant->slug}"))->toBeTrue();

    DB::enableQueryLog();

    $this->withHeader('X-Tenant-Slug', 'tienda-con-cache')
        ->getJson('/test-tenant-resolution')
        ->assertOk();

    $tenantQueries = collect(DB::getQueryLog())->filter(
        fn (array $queryLogRecord) => str_contains((string) $queryLogRecord['query'], 'tenants')
    );

    expect($tenantQueries)->toBeEmpty();
});

test('stores plain array in cache compatible with disabled serializable classes and self-heals incomplete objects', function () {
    Cache::flush();

    $tenant = Tenant::factory()->create([
        'slug' => 'tienda-redis-safe',
        'is_active' => true,
    ]);

    $this->withHeader('X-Tenant-Slug', 'tienda-redis-safe')
        ->getJson('/test-tenant-resolution')
        ->assertOk();

    $cachedPayload = Cache::get("tenant:slug:{$tenant->slug}");

    expect($cachedPayload)->toBeArray();

    $roundTripped = unserialize(serialize($cachedPayload), ['allowed_classes' => false]);

    expect($roundTripped)->toBeArray()
        ->and($roundTripped['id'])->toBe($tenant->id)
        ->and($roundTripped['slug'])->toBe('tienda-redis-safe');

    // Simulate a legacy cached Eloquent object unserialized with allowed_classes = false
    $incompleteObject = unserialize(serialize($tenant), ['allowed_classes' => false]);
    Cache::put("tenant:slug:{$tenant->slug}", $incompleteObject, 3600);

    $this->withHeader('X-Tenant-Slug', 'tienda-redis-safe')
        ->getJson('/test-tenant-resolution')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $tenant->id,
            'tenant_slug' => 'tienda-redis-safe',
        ]);
});

test('activates tenant scope automatically isolating queries to resolved tenant', function () {
    $context = app(TenantContext::class);

    $tenantA = Tenant::factory()->create([
        'slug' => 'inquilino-alfa',
        'is_active' => true,
    ]);

    $tenantB = Tenant::factory()->create([
        'slug' => 'inquilino-beta',
        'is_active' => true,
    ]);

    $context->executeAs($tenantA, function () {
        Category::create([
            'name' => 'Calzado Alfa',
            'slug' => 'calzado-alfa',
        ]);
    });

    $context->executeAs($tenantB, function () {
        Category::create([
            'name' => 'Vestimenta Beta',
            'slug' => 'vestimenta-beta',
        ]);
    });

    $context->clear();

    $response = $this->withHeader('X-Tenant-Slug', 'inquilino-alfa')
        ->getJson('/test-tenant-scope-activation');

    $response->assertStatus(200)
        ->assertJson([
            'categories' => ['Calzado Alfa'],
        ]);
});
