<?php

namespace App\Services\Catalog;

use App\Context\TenantContext;
use App\Exceptions\Domain\InsufficientStockForAdjustmentException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProductVariantRepositoryInterface;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCatalogService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected CategoryRepositoryInterface $categoryRepository,
        protected ProductRepositoryInterface $productRepository,
        protected ProductVariantRepositoryInterface $variantRepository,
        protected TenantConfigRepositoryInterface $tenantConfigRepository
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orchestrateCategoryCreation(Tenant $tenant, array $payload): Category
    {
        $parentId = $payload['parent_id'] ?? null;
        if ($parentId !== null) {
            $this->validateCategoryBelongsToTenant($parentId, $tenant->id);
        }

        $slug = ! empty($payload['slug'])
            ? Str::slug((string) $payload['slug'])
            : Str::slug((string) $payload['name']);

        $category = $this->categoryRepository->create([
            'tenant_id' => $tenant->id,
            'parent_id' => $parentId,
            'name' => $payload['name'],
            'slug' => $slug,
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);

        $this->invalidateCatalogCache($tenant);

        return $category;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getAdminCategoryTree(Tenant $tenant): Collection
    {
        return $this->categoryRepository->getTreeForAdmin();
    }

    /**
     * @return Collection<int, Category>
     */
    public function getStorefrontCategoryTree(Tenant $tenant): Collection
    {
        return $this->categoryRepository->getPublicTreeWithActiveProducts();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orchestrateProductCreation(Tenant $tenant, array $payload): Product
    {
        $schema = $this->getTenantMetadataSchema($tenant);

        $categoryId = $payload['category_id'] ?? null;
        if ($categoryId !== null) {
            $this->validateCategoryBelongsToTenant($categoryId, $tenant->id);
        }

        $productMetadata = (array) ($payload['metadata'] ?? []);
        $this->validateProductMetadataAgainstSchema($productMetadata, $schema);

        /** @var list<array<string, mixed>> $variants */
        $variants = (array) ($payload['variants'] ?? []);
        $this->validateVariantsAgainstSchema($variants, $schema);
        $this->validateUniqueVariantCombinations($variants);
        $this->ensureSingleDefaultVariant($variants);

        $productSlug = ! empty($payload['slug'])
            ? Str::slug((string) $payload['slug'])
            : Str::slug((string) $payload['name']);

        $this->generateSkusIfMissing($productSlug, $variants);

        $productData = [
            'category_id' => $categoryId,
            'name' => $payload['name'],
            'slug' => $productSlug,
            'description' => $payload['description'] ?? null,
            'base_price' => (float) $payload['base_price'],
            'images' => (array) ($payload['images'] ?? []),
            'metadata' => $productMetadata,
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ];

        $product = $this->persistProductAndVariantsTransaction($tenant, $productData, $variants);

        $this->invalidateCatalogCache($tenant);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orchestrateProductUpdate(Tenant $tenant, string $productId, array $payload): Product
    {
        $product = $this->productRepository->findByIdWithVariants($productId);
        if ($product === null) {
            throw new ModelNotFoundException('Producto no encontrado.');
        }

        $schema = $this->getTenantMetadataSchema($tenant);

        if (array_key_exists('category_id', $payload) && $payload['category_id'] !== null) {
            $this->validateCategoryBelongsToTenant((string) $payload['category_id'], $tenant->id);
        }

        if (array_key_exists('metadata', $payload)) {
            $this->validateProductMetadataAgainstSchema((array) $payload['metadata'], $schema);
        }

        /** @var list<array<string, mixed>>|null $variants */
        $variants = array_key_exists('variants', $payload)
            ? array_values((array) $payload['variants'])
            : null;

        if ($variants !== null) {
            $this->validateVariantsAgainstSchema($variants, $schema);
            $this->validateUniqueVariantCombinations($variants);
            $this->ensureSingleDefaultVariant($variants);

            $targetSlug = ! empty($payload['slug'])
                ? Str::slug((string) $payload['slug'])
                : $product->slug;

            $this->generateSkusIfMissing($targetSlug, $variants);
        }

        $updatedProduct = DB::transaction(function () use ($product, $payload, $variants): Product {
            $this->productRepository->update($product, $payload);

            if ($variants !== null) {
                $this->variantRepository->syncForProduct($product, $variants);
            }

            return $this->productRepository->findByIdWithVariants($product->id) ?? $product;
        });

        $this->invalidateCatalogCache($tenant);

        return $updatedProduct;
    }

    /**
     * @return array{variant_id: string, sku: string, previous_stock: int, current_stock: int, adjusted_by: int}
     */
    public function orchestrateStockAdjustment(Tenant $tenant, string $variantId, string $mode, int $quantity, ?string $reason = null): array
    {
        return DB::transaction(function () use ($variantId, $mode, $quantity): array {
            $variant = $this->lockVariantForUpdate($variantId);
            $previousStock = $variant->stock;
            $newStock = $this->calculateNewStock($previousStock, $mode, $quantity);

            $this->variantRepository->updateStock($variant, $newStock);
            $adjustedBy = $mode === 'set' ? ($newStock - $previousStock) : $quantity;

            return [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'previous_stock' => $previousStock,
                'current_stock' => $newStock,
                'adjusted_by' => $adjustedBy,
            ];
        });
    }

    public function orchestrateVariantStatusUpdate(Tenant $tenant, string $variantId, bool $isActive): ProductVariant
    {
        return DB::transaction(function () use ($variantId, $isActive): ProductVariant {
            $variant = $this->lockVariantForUpdate($variantId);
            $this->variantRepository->updateStatus($variant, $isActive);

            if (! $isActive && $variant->is_default) {
                $this->reassignDefaultVariantIfNeeded($variant->product, $variant);
            }

            return $variant->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchStorefrontProducts(Tenant $tenant, array $filters): LengthAwarePaginator
    {
        $schema = $this->getTenantMetadataSchema($tenant);
        $perPage = (int) ($filters['per_page'] ?? 20);

        return $this->productRepository->searchFacetedForStorefront($filters, $schema, $perPage);
    }

    public function getStorefrontProductDetail(Tenant $tenant, string $slug): Product
    {
        $product = $this->productRepository->findActiveBySlugForStorefront($slug);

        if ($product === null) {
            throw new ModelNotFoundException('Producto no encontrado.');
        }

        return $product;
    }

    public function validateCategoryBelongsToTenant(?string $categoryId, string $tenantId): void
    {
        if ($categoryId === null) {
            return;
        }

        $category = $this->categoryRepository->findById($categoryId);

        if ($category === null || $category->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'category_id' => 'La categoría seleccionada no existe o no pertenece al inquilino.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $schema
     */
    public function validateProductMetadataAgainstSchema(array $metadata, array $schema): void
    {
        $indexed = $this->indexMetadataSchema($schema);

        foreach ($metadata as $key => $value) {
            $this->validateProductMetadataAttribute((string) $key, $value, $indexed);
        }

        $this->ensureRequiredAttributesPresent($metadata, $indexed['required_product_keys'], 'metadata');
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @param  list<array<string, mixed>>  $schema
     */
    public function validateVariantsAgainstSchema(array $variants, array $schema): void
    {
        $indexed = $this->indexMetadataSchema($schema);

        foreach ($variants as $index => $variant) {
            $variantMeta = (array) ($variant['metadata'] ?? []);

            foreach ($variantMeta as $key => $value) {
                $this->validateVariantMetadataAttribute((string) $key, $value, $index, $indexed);
            }

            $this->ensureRequiredAttributesPresent($variantMeta, $indexed['required_variant_keys'], "variants.{$index}.metadata");
        }
    }

    /**
     * @param  array{by_key: array<string, array<string, mixed>>, options_lookup: array<string, array<string, bool>>}  $indexedSchema
     */
    protected function validateProductMetadataAttribute(string $key, mixed $value, array $indexedSchema): void
    {
        if (! isset($indexedSchema['by_key'][$key])) {
            throw ValidationException::withMessages([
                "metadata.{$key}" => "El atributo [{$key}] no está registrado en el esquema de metadatos.",
            ]);
        }

        $definition = $indexedSchema['by_key'][$key];

        if ((bool) ($definition['affects_stock'] ?? false)) {
            throw ValidationException::withMessages([
                "metadata.{$key}" => "El atributo [{$key}] afecta stock y no puede asignarse a los metadatos base del producto.",
            ]);
        }

        $this->validateAttributeAllowedOption(
            $key,
            $value,
            $definition,
            $indexedSchema['options_lookup'][$key] ?? null,
            'metadata'
        );
    }

    /**
     * @param  array{by_key: array<string, array<string, mixed>>, options_lookup: array<string, array<string, bool>>}  $indexedSchema
     */
    protected function validateVariantMetadataAttribute(string $key, mixed $value, int $variantIndex, array $indexedSchema): void
    {
        $fieldPrefix = "variants.{$variantIndex}.metadata";

        if (! isset($indexedSchema['by_key'][$key])) {
            throw ValidationException::withMessages([
                $fieldPrefix => "El atributo [{$key}] no está registrado en el esquema.",
            ]);
        }

        $definition = $indexedSchema['by_key'][$key];

        if (! (bool) ($definition['affects_stock'] ?? false)) {
            throw ValidationException::withMessages([
                $fieldPrefix => "El atributo [{$key}] no afecta stock y debe pertenecer a los metadatos del producto.",
            ]);
        }

        $this->validateAttributeAllowedOption(
            $key,
            $value,
            $definition,
            $indexedSchema['options_lookup'][$key] ?? null,
            $fieldPrefix
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $requiredKeys
     */
    protected function ensureRequiredAttributesPresent(array $metadata, array $requiredKeys, string $fieldPrefix): void
    {
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                $errorKey = $fieldPrefix === 'metadata'
                    ? "metadata.{$key}"
                    : "{$fieldPrefix}.{$key}";

                $message = $fieldPrefix === 'metadata'
                    ? "El atributo [{$key}] es obligatorio."
                    : "La dimensión [{$key}] es obligatoria para la variante.";

                throw ValidationException::withMessages([
                    $errorKey => $message,
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    public function validateUniqueVariantCombinations(array $variants): void
    {
        $combinations = [];

        foreach ($variants as $variant) {
            $metadata = (array) ($variant['metadata'] ?? []);
            ksort($metadata);
            $serialized = json_encode($metadata);

            if (in_array($serialized, $combinations, true)) {
                throw ValidationException::withMessages([
                    'variants' => 'No pueden coexistir dos variantes dentro del mismo producto con la misma combinación exacta de metadatos dimensionales.',
                ]);
            }

            $combinations[] = $serialized;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    public function ensureSingleDefaultVariant(array &$variants): void
    {
        $lastDefaultIndex = null;

        foreach ($variants as $index => $variant) {
            if ((bool) ($variant['is_default'] ?? false)) {
                $lastDefaultIndex = $index;
            }
        }

        if ($lastDefaultIndex === null) {
            if (isset($variants[0])) {
                $variants[0]['is_default'] = true;
            }

            return;
        }

        foreach ($variants as $index => &$variant) {
            $variant['is_default'] = ($index === $lastDefaultIndex);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    public function generateSkusIfMissing(string $productSlug, array &$variants): void
    {
        $batchSkus = [];

        foreach ($variants as $index => &$variant) {
            $sku = $this->resolveVariantSku($productSlug, $variant, $batchSkus, $index);
            $this->validateBatchSkuUniqueness($sku, $batchSkus, $index);
            $this->validateTenantSkuUniqueness($sku, $variant['id'] ?? null, $index);

            $variant['sku'] = $sku;
            $batchSkus[] = $sku;
        }
    }

    /**
     * @param  array<string, mixed>  $variant
     * @param  list<string>  $existingBatchSkus
     */
    protected function resolveVariantSku(string $productSlug, array $variant, array $existingBatchSkus, int $index): string
    {
        $sku = $variant['sku'] ?? null;

        if (empty($sku)) {
            return $this->synthesizeSkuFromMetadata(
                $productSlug,
                (array) ($variant['metadata'] ?? []),
                $existingBatchSkus,
                $index
            );
        }

        return $this->sanitizeSku((string) $sku);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $existingBatchSkus
     */
    protected function synthesizeSkuFromMetadata(
        string $productSlug,
        array $metadata,
        array $existingBatchSkus,
        int $index
    ): string {
        $parts = array_values($metadata);
        $rawSku = $productSlug.(! empty($parts) ? '-'.implode('-', $parts) : '-def');
        $generated = Str::upper(Str::slug($rawSku));

        if (in_array($generated, $existingBatchSkus, true)) {
            $generated .= '-'.($index + 1);
        }

        return $generated;
    }

    protected function sanitizeSku(string $sku): string
    {
        return Str::upper(trim($sku));
    }

    /**
     * @param  list<string>  $batchSkus
     */
    protected function validateBatchSkuUniqueness(string $sku, array $batchSkus, int $index): void
    {
        if (in_array($sku, $batchSkus, true)) {
            throw ValidationException::withMessages([
                "variants.{$index}.sku" => "El código SKU [{$sku}] se encuentra duplicado en el envío.",
            ]);
        }
    }

    protected function validateTenantSkuUniqueness(string $sku, ?string $variantId, int $index): void
    {
        if ($this->variantRepository->existsBySku($sku, $variantId)) {
            throw ValidationException::withMessages([
                "variants.{$index}.sku" => "El código SKU [{$sku}] ya está en uso en este inquilino.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $productData
     * @param  list<array<string, mixed>>  $variantsData
     */
    public function persistProductAndVariantsTransaction(Tenant $tenant, array $productData, array $variantsData): Product
    {
        return DB::transaction(function () use ($productData, $variantsData): Product {
            $product = $this->productRepository->create($productData);
            $this->variantRepository->createManyForProduct($product, $variantsData);

            return $this->productRepository->findByIdWithVariants($product->id) ?? $product;
        });
    }

    public function lockVariantForUpdate(string $variantId): ProductVariant
    {
        $variant = $this->variantRepository->findByIdWithLock($variantId);

        if ($variant === null) {
            throw new ModelNotFoundException('Variante no encontrada.');
        }

        return $variant;
    }

    public function calculateNewStock(int $currentStock, string $mode, int $quantity): int
    {
        $newStock = match ($mode) {
            'set' => $quantity,
            'adjust' => $currentStock + $quantity,
            default => throw ValidationException::withMessages(['mode' => 'Modo de ajuste inválido.']),
        };

        if ($newStock < 0) {
            throw new InsufficientStockForAdjustmentException(
                sprintf('No se puede decrementar %d unidades. El stock actual es de %d unidades.', abs($quantity), $currentStock)
            );
        }

        return $newStock;
    }

    public function reassignDefaultVariantIfNeeded(Product $product, ProductVariant $deactivatedVariant): void
    {
        $deactivatedVariant->update(['is_default' => false]);

        $nextDefault = $this->variantRepository->findFirstActiveExcept($product, $deactivatedVariant->id);
        if ($nextDefault !== null) {
            $nextDefault->update(['is_default' => true]);
        }
    }

    public function invalidateCatalogCache(Tenant $tenant): void
    {
        Cache::forget("tenant:{$tenant->id}:storefront_categories");
        Cache::forget("tenant:{$tenant->id}:storefront_config");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTenantMetadataSchema(Tenant $tenant): array
    {
        $config = $this->tenantConfigRepository->findByTenantId($tenant->id);

        return (array) ($config->metadata_schema ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     * @return array{
     *     by_key: array<string, array<string, mixed>>,
     *     required_product_keys: list<string>,
     *     required_variant_keys: list<string>,
     *     options_lookup: array<string, array<string, bool>>
     * }
     */
    protected function indexMetadataSchema(array $schema): array
    {
        $byKey = [];
        $requiredProductKeys = [];
        $requiredVariantKeys = [];
        $optionsLookup = [];

        foreach ($schema as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $byKey[$key] = $definition;
            $isRequired = (bool) ($definition['is_required'] ?? false);
            $affectsStock = (bool) ($definition['affects_stock'] ?? false);

            if ($isRequired) {
                if ($affectsStock) {
                    $requiredVariantKeys[] = $key;
                } else {
                    $requiredProductKeys[] = $key;
                }
            }

            $dataType = (string) ($definition['data_type'] ?? 'text');
            if (in_array($dataType, ['select', 'multiselect'], true)) {
                $options = (array) ($definition['options'] ?? []);
                $lookup = [];
                foreach ($options as $option) {
                    $lookup[(string) $option] = true;
                }
                $optionsLookup[$key] = $lookup;
            }
        }

        return [
            'by_key' => $byKey,
            'required_product_keys' => $requiredProductKeys,
            'required_variant_keys' => $requiredVariantKeys,
            'options_lookup' => $optionsLookup,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, bool>|null  $optionsLookup
     */
    protected function validateAttributeAllowedOption(
        string $key,
        mixed $value,
        array $definition,
        ?array $optionsLookup,
        string $fieldPrefix
    ): void {
        if ($optionsLookup === null) {
            return;
        }

        $dataType = (string) ($definition['data_type'] ?? 'text');

        if ($dataType === 'select') {
            if (! isset($optionsLookup[(string) $value])) {
                throw ValidationException::withMessages([
                    "{$fieldPrefix}.{$key}" => "El valor [{$value}] no es válido para la opción [{$key}].",
                ]);
            }

            return;
        }

        $values = is_array($value) ? $value : [$value];
        foreach ($values as $val) {
            if (! isset($optionsLookup[(string) $val])) {
                throw ValidationException::withMessages([
                    "{$fieldPrefix}.{$key}" => "El valor [{$val}] no es válido para la opción [{$key}].",
                ]);
            }
        }
    }

    /**
     * Formats a product with its variants for standard API responses.
     *
     * @return array<string, mixed>
     */
    public function formatProductForAdminResponse(Product $product): array
    {
        $variants = $product->variants->map(function (ProductVariant $variant) use ($product): array {
            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->effectivePrice((float) $product->base_price),
                'price_override' => $variant->price !== null ? (float) $variant->price : null,
                'stock' => (int) $variant->stock,
                'metadata' => (array) ($variant->metadata ?? []),
                'is_default' => (bool) $variant->is_default,
                'is_active' => (bool) $variant->is_active,
            ];
        })->all();

        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'base_price' => (float) $product->base_price,
            'metadata' => (array) ($product->metadata ?? []),
            'images' => (array) ($product->images ?? []),
            'is_active' => (bool) $product->is_active,
            'variants_count' => (int) ($product->variants_count ?? count($variants)),
            'total_stock' => (int) ($product->total_stock ?? array_sum(array_column($variants, 'stock'))),
            'variants' => $variants,
        ];
    }

    /**
     * Formats a product for storefront search response.
     *
     * @return array<string, mixed>
     */
    public function formatProductForStorefrontResponse(Product $product): array
    {
        $matchedVariants = $product->variants->map(function (ProductVariant $variant) use ($product): array {
            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'effective_price' => $variant->effectivePrice((float) $product->base_price),
                'stock' => (int) $variant->stock,
                'is_default' => (bool) $variant->is_default,
                'metadata' => (array) ($variant->metadata ?? []),
            ];
        })->all();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'base_price' => (float) $product->base_price,
            'metadata' => (array) ($product->metadata ?? []),
            'images' => (array) ($product->images ?? []),
            'matched_variants' => $matchedVariants,
        ];
    }
}
