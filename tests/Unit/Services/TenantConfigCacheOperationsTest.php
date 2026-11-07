<?php

use App\Models\Tenant;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use App\Services\Tenant\TenantConfigService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->repository = Mockery::mock(TenantConfigRepositoryInterface::class);
    $this->service = new TenantConfigService($this->repository);

    $this->tenant = new Tenant([
        'name' => 'Tienda Caché',
        'slug' => 'tienda-cache',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
        'is_active' => true,
    ]);
    $this->tenant->id = '990e8400-e29b-41d4-a716-446655440000';
});

test('construye la clave de cache de vitrina con el tenant_id correcto', function () {
    $expectedKey = "tenant:{$this->tenant->id}:storefront_config";

    expect($this->service->getStorefrontCacheKey($this->tenant))->toBe($expectedKey);
});

test('resolveFromRedisCache retorna null en cache miss y el array en cache hit', function () {
    expect($this->service->resolveFromRedisCache($this->tenant))->toBeNull();

    $cacheKey = $this->service->getStorefrontCacheKey($this->tenant);
    $payload = ['tenant' => ['name' => 'Tienda Caché']];
    Cache::put($cacheKey, $payload, 3600);

    expect($this->service->resolveFromRedisCache($this->tenant))->toBe($payload);
});

test('storeInRedisCache almacena payload con TTL de 3600 segundos', function () {
    $payload = ['tenant' => ['name' => 'Tienda Caché']];
    $cacheKey = $this->service->getStorefrontCacheKey($this->tenant);

    $this->service->storeInRedisCache($this->tenant, $payload);

    expect(Cache::has($cacheKey))->toBeTrue()
        ->and(Cache::get($cacheKey))->toBe($payload);
});

test('invalidateStorefrontCache purga la clave de vitrina de la cache', function () {
    $cacheKey = $this->service->getStorefrontCacheKey($this->tenant);
    Cache::put($cacheKey, ['test' => 'data'], 3600);

    expect(Cache::has($cacheKey))->toBeTrue();

    $this->service->invalidateStorefrontCache($this->tenant);

    expect(Cache::has($cacheKey))->toBeFalse();
});
