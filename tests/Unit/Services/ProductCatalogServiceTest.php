<?php

use App\Services\Catalog\ProductCatalogService;
use Illuminate\Validation\ValidationException;

test('validador de combinaciones detecta duplicados en matrices de variantes en memoria', function () {
    $service = app(ProductCatalogService::class);

    $variantsWithDuplicates = [
        [
            'sku' => 'VAR-1',
            'metadata' => ['talla' => '40', 'color' => 'Negro'],
        ],
        [
            'sku' => 'VAR-2',
            'metadata' => ['color' => 'Negro', 'talla' => '40'],
        ],
    ];

    expect(fn () => $service->validateUniqueVariantCombinations($variantsWithDuplicates))
        ->toThrow(ValidationException::class);
});

test('validador de combinaciones acepta combinaciones dimensionales distintas', function () {
    $service = app(ProductCatalogService::class);

    $distinctVariants = [
        [
            'sku' => 'VAR-1',
            'metadata' => ['talla' => '39', 'color' => 'Negro'],
        ],
        [
            'sku' => 'VAR-2',
            'metadata' => ['talla' => '40', 'color' => 'Negro'],
        ],
    ];

    $service->validateUniqueVariantCombinations($distinctVariants);

    expect(true)->toBeTrue();
});

test('generador de SKU sintetiza codigos limpios en mayusculas a partir del slug y atributos', function () {
    $service = app(ProductCatalogService::class);

    $variants = [
        [
            'sku' => null,
            'metadata' => ['talla' => '39', 'color' => 'Azul Marino'],
        ],
    ];

    $service->generateSkusIfMissing('camisa-oxford', $variants);

    expect($variants[0]['sku'])->toBe('CAMISA-OXFORD-39-AZUL-MARINO');
});

test('ensureSingleDefaultVariant promueve la primera variante cuando ninguna viene marcada', function () {
    $service = app(ProductCatalogService::class);

    $variants = [
        ['sku' => 'V1', 'is_default' => false],
        ['sku' => 'V2', 'is_default' => false],
    ];

    $service->ensureSingleDefaultVariant($variants);

    expect($variants[0]['is_default'])->toBeTrue()
        ->and($variants[1]['is_default'])->toBeFalse();
});

test('ensureSingleDefaultVariant conserva unicamente la ultima marcada cuando vienen multiples defaults', function () {
    $service = app(ProductCatalogService::class);

    $variants = [
        ['sku' => 'V1', 'is_default' => true],
        ['sku' => 'V2', 'is_default' => true],
    ];

    $service->ensureSingleDefaultVariant($variants);

    expect($variants[0]['is_default'])->toBeFalse()
        ->and($variants[1]['is_default'])->toBeTrue();
});

test('validateVariantsAgainstSchema rechaza variantes con atributos marcados con affects_stock false', function () {
    $service = app(ProductCatalogService::class);

    $schema = [
        [
            'key' => 'talla',
            'label' => 'Talla',
            'data_type' => 'select',
            'options' => ['S', 'M'],
            'is_required' => true,
            'affects_stock' => true,
        ],
        [
            'key' => 'material',
            'label' => 'Material',
            'data_type' => 'text',
            'options' => null,
            'is_required' => false,
            'affects_stock' => false,
        ],
    ];

    $invalidVariants = [
        [
            'sku' => 'V1',
            'metadata' => [
                'talla' => 'M',
                'material' => 'Algodon',
            ],
        ],
    ];

    expect(fn () => $service->validateVariantsAgainstSchema($invalidVariants, $schema))
        ->toThrow(ValidationException::class);
});

test('validateVariantsAgainstSchema rechaza valores que no pertenecen a options en campos select', function () {
    $service = app(ProductCatalogService::class);

    $schema = [
        [
            'key' => 'talla',
            'label' => 'Talla',
            'data_type' => 'select',
            'options' => ['38', '39', '40'],
            'is_required' => true,
            'affects_stock' => true,
        ],
    ];

    $invalidVariants = [
        [
            'sku' => 'V1',
            'metadata' => [
                'talla' => '48',
            ],
        ],
    ];

    expect(fn () => $service->validateVariantsAgainstSchema($invalidVariants, $schema))
        ->toThrow(ValidationException::class);
});

test('validateProductMetadataAgainstSchema rechaza atributos con affects_stock true en el producto base', function () {
    $service = app(ProductCatalogService::class);

    $schema = [
        [
            'key' => 'talla',
            'label' => 'Talla',
            'data_type' => 'select',
            'options' => ['S', 'M'],
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
    ];

    $invalidProductMetadata = [
        'material' => 'Cuero',
        'talla' => 'M',
    ];

    expect(fn () => $service->validateProductMetadataAgainstSchema($invalidProductMetadata, $schema))
        ->toThrow(ValidationException::class);
});
