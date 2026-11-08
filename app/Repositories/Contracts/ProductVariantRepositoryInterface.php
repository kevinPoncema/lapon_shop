<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

interface ProductVariantRepositoryInterface
{
    public function findById(string $id): ?ProductVariant;

    public function findByIdWithLock(string $id): ?ProductVariant;

    public function findByIdWithLockIncludingTrashed(string $id): ?ProductVariant;

    /**
     * @param  list<string>  $variantIds
     * @return Collection<int, ProductVariant>
     */
    public function findManyActiveWithProduct(array $variantIds): Collection;

    /**
     * @param  list<string>  $variantIds
     * @return Collection<int, ProductVariant>
     */
    public function findManyWithLock(array $variantIds): Collection;

    public function decrementStock(ProductVariant $variant, int $quantity): void;

    public function incrementStock(ProductVariant $variant, int $quantity): void;

    public function existsBySku(string $sku, ?string $ignoreVariantId = null): bool;

    /**
     * @param  list<array<string, mixed>>  $variantsData
     * @return Collection<int, ProductVariant>
     */
    public function createManyForProduct(Product $product, array $variantsData): Collection;

    /**
     * @param  list<array<string, mixed>>  $variantsData
     * @return Collection<int, ProductVariant>
     */
    public function syncForProduct(Product $product, array $variantsData): Collection;

    public function updateStock(ProductVariant $variant, int $newStock): ProductVariant;

    public function updateStatus(ProductVariant $variant, bool $isActive): ProductVariant;

    public function findFirstActiveExcept(Product $product, string $excludedVariantId): ?ProductVariant;
}
