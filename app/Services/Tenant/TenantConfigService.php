<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantConfigService
{
    /**
     * Cache TTL in seconds for storefront bootstrap data.
     */
    public const STOREFRONT_CACHE_TTL = 3600;

    /**
     * Reserved system keywords strictly forbidden as metadata dynamic keys.
     *
     * @var list<string>
     */
    public const RESERVED_KEYWORDS = [
        'id',
        'tenant_id',
        'name',
        'slug',
        'description',
        'category',
        'category_id',
        'sku',
        'price',
        'base_price',
        'stock',
        'status',
        'is_active',
        'created_at',
        'updated_at',
        'deleted_at',
        'metadata',
        'images',
    ];

    /**
     * Supported metadata schema data types.
     *
     * @var list<string>
     */
    public const ALLOWED_DATA_TYPES = [
        'text',
        'number',
        'boolean',
        'select',
        'multiselect',
        'date',
    ];

    public function __construct(
        protected TenantConfigRepositoryInterface $repository
    ) {}

    /**
     * Retrieve complete admin configuration for the authenticated tenant.
     */
    public function getAdminConfig(Tenant $tenant): TenantConfig
    {
        return $this->repository->getOrCreateByTenantId($tenant->id);
    }

    /**
     * Orchestrator Master 1: Coordinate catalog schema and filter configuration updates.
     *
     * @param  array{metadata_schema?: list<array<string, mixed>>, filter_config?: list<array<string, mixed>>}  $payload
     */
    public function orchestrateCatalogSchemaUpdate(Tenant $tenant, array $payload): TenantConfig
    {
        $existingConfig = $this->repository->getOrCreateByTenantId($tenant->id);

        /** @var list<array<string, mixed>> $metadataSchema */
        $metadataSchema = array_key_exists('metadata_schema', $payload)
            ? (array) $payload['metadata_schema']
            : (array) ($existingConfig->metadata_schema ?? []);

        /** @var list<array<string, mixed>> $filterConfig */
        $filterConfig = array_key_exists('filter_config', $payload)
            ? (array) $payload['filter_config']
            : (array) ($existingConfig->filter_config ?? []);

        $this->validateReservedKeywords($metadataSchema);
        $this->validateAttributeDataTypes($metadataSchema);
        $sanitizedFilters = $this->purgeOrphanFilters($metadataSchema, $filterConfig);
        $updatedConfig = DB::transaction(fn (): TenantConfig => $this->persistCatalogSchema(
            $tenant,
            $metadataSchema,
            $sanitizedFilters
        ));

        $this->invalidateStorefrontCache($tenant);

        return $updatedConfig;
    }

    /**
     * Orchestrator Master 2: Coordinate branding updates.
     *
     * @param  array<string, mixed>  $payload
     */
    public function orchestrateBrandingUpdate(Tenant $tenant, array $payload): TenantConfig
    {
        $sanitizedBranding = $this->sanitizeBrandingPayload($payload);
        $updatedConfig = DB::transaction(fn (): TenantConfig => $this->persistBranding(
            $tenant,
            $sanitizedBranding
        ));

        $this->invalidateStorefrontCache($tenant);

        return $updatedConfig;
    }

    /**
     * Orchestrator Master 3: Coordinate shipping zones updates.
     *
     * @param  array<string, mixed>  $payload
     */
    public function orchestrateShippingZonesUpdate(Tenant $tenant, array $payload): TenantConfig
    {
        /** @var list<array<string, mixed>> $zones */
        $zones = (array) ($payload['shipping_zones'] ?? []);
        $this->validateShippingZones($zones);
        $updatedConfig = DB::transaction(fn (): TenantConfig => $this->persistShippingZones(
            $tenant,
            $zones
        ));
        $this->invalidateStorefrontCache($tenant);

        return $updatedConfig;
    }

    /**
     * Orchestrator Master 4: Retrieve public storefront bootstrap data with Cache-Aside pattern.
     *
     * @return array<string, mixed>
     */
    public function getStorefrontBootstrap(Tenant $tenant): array
    {
        $cached = $this->resolveFromRedisCache($tenant);

        if ($cached !== null) {
            return $cached;
        }

        $config = $this->repository->findByTenantId($tenant->id);
        $payload = $this->buildStorefrontPayload($tenant, $config);

        $this->storeInRedisCache($tenant, $payload);

        return $payload;
    }

    /**
     * Valida que no se utilicen nombres del sistema como claves de metadatos dinámicos.
     *
     * @param  list<array<string, mixed>>  $metadataSchema
     *
     * @throws ValidationException
     */
    public function validateReservedKeywords(array $metadataSchema): void
    {
        foreach ($metadataSchema as $index => $attribute) {
            $key = (string) ($attribute['key'] ?? '');

            if (in_array(Str::lower($key), self::RESERVED_KEYWORDS, true)) {
                throw ValidationException::withMessages([
                    "metadata_schema.{$index}.key" => "La clave {$key} es una palabra reservada del sistema y no puede ser utilizada como metadato dinámico.",
                ]);
            }
        }
    }

    /**
     * Valida los tipos de datos permitidos y la obligatoriedad de options en selects.
     *
     * @param  list<array<string, mixed>>  $metadataSchema
     *
     * @throws ValidationException
     */
    public function validateAttributeDataTypes(array $metadataSchema): void
    {
        foreach ($metadataSchema as $index => $attribute) {
            $dataType = (string) ($attribute['data_type'] ?? '');

            if (! in_array($dataType, self::ALLOWED_DATA_TYPES, true)) {
                throw ValidationException::withMessages([
                    "metadata_schema.{$index}.data_type" => "El tipo de dato [{$dataType}] no está permitido.",
                ]);
            }

            if (in_array($dataType, ['select', 'multiselect'], true)) {
                $options = $attribute['options'] ?? null;

                if (! is_array($options) || count($options) === 0) {
                    throw ValidationException::withMessages([
                        "metadata_schema.{$index}.options" => 'El campo opciones es requerido para atributos de tipo select o multiselect.',
                    ]);
                }
            }
        }
    }

    /**
     * Sanea la configuración de filtros descartando filtros metadata que no existan en metadata_schema.
     *
     * @param  list<array<string, mixed>>  $metadataSchema
     * @param  list<array<string, mixed>>  $filterConfig
     * @return list<array<string, mixed>>
     */
    public function purgeOrphanFilters(array $metadataSchema, array $filterConfig): array
    {
        $validMetadataKeys = collect($metadataSchema)
            ->pluck('key')
            ->filter(fn ($k) => is_string($k) && $k !== '')
            ->all();

        $sanitized = collect($filterConfig)->filter(function (array $filter) use ($validMetadataKeys): bool {
            $type = (string) ($filter['type'] ?? 'system');
            $key = (string) ($filter['key'] ?? '');

            if ($type === 'metadata') {
                return in_array($key, $validMetadataKeys, true);
            }

            return true;
        })->values()->all();

        /** @var list<array<string, mixed>> $result */
        $result = array_values($sanitized);

        return $result;
    }

    /**
     * Normaliza y sanea el payload de branding (colores hex a mayúsculas, cadenas limpias).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizeBrandingPayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array($key, ['primary_color', 'secondary_color', 'accent_color'], true) && is_string($value)) {
                $sanitized[$key] = Str::upper(trim($value));

                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = trim($value);

                continue;
            }

            if ($key === 'social_links' && is_array($value)) {
                $sanitized[$key] = collect($value)
                    ->map(fn ($val) => is_string($val) ? trim($val) : $val)
                    ->all();

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Valida que las zonas de envío tengan códigos únicos, nombres válidos y tarifas >= 0.
     *
     * @param  list<array<string, mixed>>  $zones
     *
     * @throws ValidationException
     */
    public function validateShippingZones(array $zones): void
    {
        $seenCodes = [];

        foreach ($zones as $index => $zone) {
            $code = trim((string) ($zone['code'] ?? ''));
            $name = trim((string) ($zone['name'] ?? ''));
            $fee = $zone['fee'] ?? null;

            if ($code === '') {
                throw ValidationException::withMessages([
                    "shipping_zones.{$index}.code" => 'El código de la zona de envío es requerido.',
                ]);
            }

            $normalizedCode = Str::upper($code);
            if (in_array($normalizedCode, $seenCodes, true)) {
                throw ValidationException::withMessages([
                    "shipping_zones.{$index}.code" => "El código de zona [{$code}] se encuentra duplicado.",
                ]);
            }
            $seenCodes[] = $normalizedCode;

            if ($name === '') {
                throw ValidationException::withMessages([
                    "shipping_zones.{$index}.name" => 'El nombre descriptivo de la zona de envío es requerido.',
                ]);
            }

            if (! is_numeric($fee) || (float) $fee < 0) {
                throw ValidationException::withMessages([
                    "shipping_zones.{$index}.fee" => 'La tarifa de despacho no puede ser un monto negativo.',
                ]);
            }
        }
    }

    /**
     * Construye el payload público de vitrina enriqueciendo filtros y excluyendo datos sensibles.
     *
     * @return array<string, mixed>
     */
    public function buildStorefrontPayload(Tenant $tenant, ?TenantConfig $config): array
    {
        return [
            'tenant' => $this->buildStorefrontTenant($tenant),
            'branding' => (array) ($config->branding ?? []),
            'filters' => $this->buildStorefrontFilters($config),
            'shipping_zones' => $this->buildStorefrontShippingZones($config),
        ];
    }

    /**
     * Construye la información de identidad del tenant para la vitrina pública.
     *
     * @return array{name: string, currency_code: string, country_code: string}
     */
    protected function buildStorefrontTenant(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'currency_code' => $tenant->currency_code,
            'country_code' => $tenant->country_code,
        ];
    }

    /**
     * Construye y enriquece los filtros públicos a partir del filter_config y metadata_schema.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildStorefrontFilters(?TenantConfig $config): array
    {
        $metadataSchema = (array) ($config->metadata_schema ?? []);
        $filterConfig = (array) ($config->filter_config ?? []);

        // Mapear esquema dinámico por clave para enriquecer filtros
        $schemaByKey = collect($metadataSchema)->keyBy('key')->all();

        /** @var list<array<string, mixed>> $filters */
        $filters = collect($filterConfig)
            ->filter(function (array $filter) use ($schemaByKey): bool {
                $type = (string) ($filter['type'] ?? 'system');
                $key = (string) ($filter['key'] ?? '');

                return $type !== 'metadata' || isset($schemaByKey[$key]);
            })
            ->map(function (array $filter, int $index) use ($schemaByKey): array {
                $type = (string) ($filter['type'] ?? 'system');
                $order = (int) ($filter['order'] ?? ($index + 1));

                if ($type === 'metadata') {
                    $key = (string) ($filter['key'] ?? '');

                    return $this->enrichMetadataFilter($filter, (array) ($schemaByKey[$key] ?? []), $order);
                }

                return $this->enrichSystemFilter($filter, $order);
            })
            ->sortBy('order')
            ->values()
            ->all();

        return $filters;
    }

    /**
     * Enriquece un filtro de tipo metadata con propiedades del esquema dinámico.
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function enrichMetadataFilter(array $filter, array $meta, int $order): array
    {
        $key = (string) ($filter['key'] ?? '');
        $dataType = (string) ($meta['data_type'] ?? 'text');

        $item = [
            'type' => 'metadata',
            'key' => $key,
            'label' => (string) ($filter['label'] ?? $meta['label'] ?? $key),
            'data_type' => $dataType,
            'affects_stock' => (bool) ($meta['affects_stock'] ?? false),
            'order' => $order,
        ];

        if (in_array($dataType, ['select', 'multiselect'], true) && isset($meta['options'])) {
            $item['options'] = $meta['options'];
        }

        return $item;
    }

    /**
     * Enriquece un filtro de tipo system con valores por defecto e inferencia de data_type.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    protected function enrichSystemFilter(array $filter, int $order): array
    {
        $key = (string) ($filter['key'] ?? '');
        $dataType = (string) ($filter['data_type'] ?? match ($key) {
            'price' => 'range',
            'category' => 'select',
            default => 'text',
        });

        return [
            'type' => 'system',
            'key' => $key,
            'label' => (string) ($filter['label'] ?? ucfirst($key)),
            'data_type' => $dataType,
            'order' => $order,
        ];
    }

    /**
     * Formatea las zonas de despacho públicas con tarifas normalizadas a número de punto flotante.
     *
     * @return list<array{code: string, name: string, fee: float}>
     */
    protected function buildStorefrontShippingZones(?TenantConfig $config): array
    {
        /** @var list<array{code: string, name: string, fee: float}> $zones */
        $zones = collect((array) ($config->shipping_zones ?? []))
            ->map(fn (array $z): array => [
                'code' => (string) ($z['code'] ?? ''),
                'name' => (string) ($z['name'] ?? ''),
                'fee' => (float) ($z['fee'] ?? 0.00),
            ])
            ->values()
            ->all();

        return $zones;
    }

    /**
     * Clave única de almacenamiento de vitrina en Redis.
     */
    public function getStorefrontCacheKey(Tenant $tenant): string
    {
        return "tenant:{$tenant->id}:storefront_config";
    }

    /**
     * Intenta resolver la configuración pública desde la caché de Redis.
     *
     * @return array<string, mixed>|null
     */
    public function resolveFromRedisCache(Tenant $tenant): ?array
    {
        $data = Cache::get($this->getStorefrontCacheKey($tenant));

        return is_array($data) ? $data : null;
    }

    /**
     * Almacena en la caché de Redis la configuración pública de vitrina.
     *
     * @param  array<string, mixed>  $payload
     */
    public function storeInRedisCache(Tenant $tenant, array $payload): void
    {
        Cache::put(
            $this->getStorefrontCacheKey($tenant),
            $payload,
            self::STOREFRONT_CACHE_TTL
        );
    }

    /**
     * Invalida la clave de vitrina pública en Redis.
     */
    public function invalidateStorefrontCache(Tenant $tenant): void
    {
        Cache::forget($this->getStorefrontCacheKey($tenant));
    }

    /**
     * Persiste la configuración de catálogo en el repositorio.
     *
     * @param  list<array<string, mixed>>  $metadataSchema
     * @param  list<array<string, mixed>>  $filterConfig
     */
    protected function persistCatalogSchema(Tenant $tenant, array $metadataSchema, array $filterConfig): TenantConfig
    {
        return $this->repository->updateCatalogSchema($tenant->id, $metadataSchema, $filterConfig);
    }

    /**
     * Persiste los datos de branding en el repositorio.
     *
     * @param  array<string, mixed>  $brandingData
     */
    protected function persistBranding(Tenant $tenant, array $brandingData): TenantConfig
    {
        return $this->repository->updateBranding($tenant->id, $brandingData);
    }

    /**
     * Persiste las zonas de despacho en el repositorio.
     *
     * @param  list<array<string, mixed>>  $zones
     */
    protected function persistShippingZones(Tenant $tenant, array $zones): TenantConfig
    {
        return $this->repository->updateShippingZones($tenant->id, $zones);
    }
}
