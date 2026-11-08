<?php

use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use App\Services\Tenant\TenantConfigService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->repository = Mockery::mock(TenantConfigRepositoryInterface::class);
    $this->service = new TenantConfigService($this->repository);
});

test('validateReservedKeywords detecta y rechaza palabras reservadas del sistema', function (string $reservedKey) {
    $schema = [
        [
            'key' => $reservedKey,
            'label' => ucfirst($reservedKey),
            'data_type' => 'text',
        ],
    ];

    expect(fn () => $this->service->validateReservedKeywords($schema))
        ->toThrow(
            ValidationException::class,
            "La clave {$reservedKey} es una palabra reservada del sistema y no puede ser utilizada como metadato dinámico."
        );
})->with([
    'id',
    'tenant_id',
    'name',
    'slug',
    'description',
    'category_id',
    'sku',
    'price',
    'stock',
    'status',
    'is_active',
    'created_at',
    'updated_at',
    'deleted_at',
    'metadata',
]);

test('validateReservedKeywords permite claves validas y no reservadas', function () {
    $schema = [
        [
            'key' => 'talla',
            'label' => 'Talla',
            'data_type' => 'select',
            'options' => ['38', '39', '40'],
        ],
        [
            'key' => 'material_exterior',
            'label' => 'Material Exterior',
            'data_type' => 'text',
        ],
    ];

    $this->service->validateReservedKeywords($schema);
    expect(true)->toBeTrue();
});

test('validateAttributeDataTypes valida tipos permitidos y obligatoriedad de options en selects', function () {
    // Tipo select sin options
    $invalidSchema = [
        [
            'key' => 'talla',
            'label' => 'Talla',
            'data_type' => 'select',
            'options' => [],
        ],
    ];

    expect(fn () => $this->service->validateAttributeDataTypes($invalidSchema))
        ->toThrow(ValidationException::class);

    // Tipo desconocido
    $unsupportedTypeSchema = [
        [
            'key' => 'peso',
            'label' => 'Peso',
            'data_type' => 'unsupported_type',
        ],
    ];

    expect(fn () => $this->service->validateAttributeDataTypes($unsupportedTypeSchema))
        ->toThrow(ValidationException::class);

    // Schema valido con tipos soportados
    $validSchema = [
        ['key' => 'talla', 'label' => 'Talla', 'data_type' => 'select', 'options' => ['S', 'M']],
        ['key' => 'tags', 'label' => 'Etiquetas', 'data_type' => 'multiselect', 'options' => ['Nuevo', 'Oferta']],
        ['key' => 'material', 'label' => 'Material', 'data_type' => 'text'],
        ['key' => 'garantia_meses', 'label' => 'Garantía', 'data_type' => 'number'],
        ['key' => 'es_impermeable', 'label' => 'Impermeable', 'data_type' => 'boolean'],
        ['key' => 'fecha_lanzamiento', 'label' => 'Lanzamiento', 'data_type' => 'date'],
    ];

    $this->service->validateAttributeDataTypes($validSchema);
    expect(true)->toBeTrue();
});

test('purgeOrphanFilters sanea un arreglo de filtros descartando aquellos que apunten a claves eliminadas', function () {
    $metadataSchema = [
        [
            'key' => 'talla',
            'label' => 'Talla',
            'data_type' => 'select',
            'options' => ['38', '39'],
            'affects_stock' => true,
        ],
    ];

    $filterConfig = [
        ['type' => 'system', 'key' => 'price', 'label' => 'Precio', 'order' => 1],
        ['type' => 'metadata', 'key' => 'talla', 'label' => 'Talla', 'order' => 2],
        ['type' => 'metadata', 'key' => 'color_inexistente', 'label' => 'Color', 'order' => 3],
        ['type' => 'system', 'key' => 'category', 'label' => 'Categoría', 'order' => 4],
    ];

    $purged = $this->service->purgeOrphanFilters($metadataSchema, $filterConfig);

    expect($purged)->toHaveCount(3)
        ->and(collect($purged)->pluck('key')->all())->toBe(['price', 'talla', 'category']);
});

test('sanitizeBrandingPayload normaliza colores hex a mayusculas y limpia cadenas', function () {
    $payload = [
        'primary_color' => '#1e293b',
        'secondary_color' => '#475569',
        'accent_color' => '#f59e0b',
        'font_family' => '  Plus Jakarta Sans  ',
        'logo_url' => 'https://cdn.plataforma.com/t1/logo.webp',
        'banner_url' => 'https://cdn.plataforma.com/t1/banner.webp',
        'favicon_url' => 'https://cdn.plataforma.com/t1/favicon.ico',
        'social_links' => [
            'whatsapp' => ' +50499001122 ',
            'instagram' => ' mitienda_hn ',
        ],
    ];

    $sanitized = $this->service->sanitizeBrandingPayload($payload);

    expect($sanitized['primary_color'])->toBe('#1E293B')
        ->and($sanitized['secondary_color'])->toBe('#475569')
        ->and($sanitized['accent_color'])->toBe('#F59E0B')
        ->and($sanitized['font_family'])->toBe('Plus Jakarta Sans')
        ->and($sanitized['social_links']['whatsapp'])->toBe('+50499001122')
        ->and($sanitized['social_links']['instagram'])->toBe('mitienda_hn');
});

test('validateShippingZones rechaza tarifas negativas o zonas sin codigo ni nombre', function () {
    $negativeFeeZones = [
        ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => -10.00],
    ];

    expect(fn () => $this->service->validateShippingZones($negativeFeeZones))
        ->toThrow(ValidationException::class);

    $missingCodeZones = [
        ['code' => '', 'name' => 'Cortés', 'fee' => 80.00],
    ];

    expect(fn () => $this->service->validateShippingZones($missingCodeZones))
        ->toThrow(ValidationException::class);

    $duplicateCodeZones = [
        ['code' => 'HN-CR', 'name' => 'Cortés 1', 'fee' => 80.00],
        ['code' => 'HN-CR', 'name' => 'Cortés 2', 'fee' => 90.00],
    ];

    expect(fn () => $this->service->validateShippingZones($duplicateCodeZones))
        ->toThrow(ValidationException::class);

    $validZones = [
        ['code' => 'HN-CR', 'name' => 'Cortés', 'fee' => 0.00], // Free shipping permitido
        ['code' => 'HN-FM', 'name' => 'Francisco Morazán', 'fee' => 120.50],
    ];

    $this->service->validateShippingZones($validZones);
    expect(true)->toBeTrue();
});
