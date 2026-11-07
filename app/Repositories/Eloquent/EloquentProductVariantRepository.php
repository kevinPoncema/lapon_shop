<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ProductVariantRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentProductVariantRepository implements ProductVariantRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findById(string $id): ?ProductVariant
    {
        return ProductVariant::query()->with('product')->find($id);
    }

    public function findByIdWithLock(string $id): ?ProductVariant
    {
        return ProductVariant::query()
            ->with('product')
            ->where('id', $id)
            ->lockForUpdate()
            ->first();
    }

    public function findByIdWithLockIncludingTrashed(string $id): ?ProductVariant
    {
        return ProductVariant::withTrashed()
            ->with('product')
            ->where('id', $id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  list<string>  $variantIds
     * @return Collection<int, ProductVariant>
     */
    public function findManyActiveWithProduct(array $variantIds): Collection
    {
        if (empty($variantIds)) {
            return new Collection;
        }

        return ProductVariant::query()
            ->with('product')
            ->whereIn('id', $variantIds)
            ->where('is_active', true)
            ->whereHas('product', function ($query) {
                $query->where('is_active', true);
            })
            ->get();
    }

    /**
     * @param  list<string>  $variantIds
     * @return Collection<int, ProductVariant>
     */
    public function findManyWithLock(array $variantIds): Collection
    {
        if (empty($variantIds)) {
            return new Collection;
        }

        return ProductVariant::query()
            ->with('product')
            ->whereIn('id', $variantIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();
    }

    public function decrementStock(ProductVariant $variant, int $quantity): void
    {
        $variant->decrement('stock', $quantity);
    }

    public function incrementStock(ProductVariant $variant, int $quantity): void
    {
        $variant->increment('stock', $quantity);
    }

    public function existsBySku(string $sku, ?string $ignoreVariantId = null): bool
    {
        return ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreVariantId !== null, fn ($query) => $query->where('id', '!=', $ignoreVariantId))
            ->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $variantsData
     * @return Collection<int, ProductVariant>
     */
    public function createManyForProduct(Product $product, array $variantsData): Collection
    {
        $created = new Collection;

        foreach ($variantsData as $variantData) {
            $created->push($product->variants()->create([
                'tenant_id' => $this->tenantContext->id(),
                'sku' => $variantData['sku'],
                'price' => $variantData['price'] ?? null,
                'stock' => (int) ($variantData['stock'] ?? 0),
                'metadata' => $variantData['metadata'] ?? [],
                'is_default' => (bool) ($variantData['is_default'] ?? false),
                'is_active' => (bool) ($variantData['is_active'] ?? true),
            ]));
        }

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $variantsData
     * @return Collection<int, ProductVariant>
     */
    public function syncForProduct(Product $product, array $variantsData): Collection
    {
        $processedIds = [];
        $synced = new Collection;

        foreach ($variantsData as $variantData) {
            $variantId = $variantData['id'] ?? null;

            if ($variantId !== null) {
                $variant = $product->variants()->where('id', $variantId)->first();

                if ($variant !== null) {
                    $variant->update([
                        'sku' => $variantData['sku'] ?? $variant->sku,
                        'price' => array_key_exists('price', $variantData) ? $variantData['price'] : $variant->price,
                        'stock' => array_key_exists('stock', $variantData) ? (int) $variantData['stock'] : $variant->stock,
                        'metadata' => array_key_exists('metadata', $variantData) ? (array) $variantData['metadata'] : $variant->metadata,
                        'is_default' => array_key_exists('is_default', $variantData) ? (bool) $variantData['is_default'] : $variant->is_default,
                        'is_active' => array_key_exists('is_active', $variantData) ? (bool) $variantData['is_active'] : $variant->is_active,
                    ]);

                    $processedIds[] = $variant->id;
                    $synced->push($variant->fresh());

                    continue;
                }
            }

            $newVariant = $product->variants()->create([
                'tenant_id' => $this->tenantContext->id(),
                'sku' => $variantData['sku'],
                'price' => $variantData['price'] ?? null,
                'stock' => (int) ($variantData['stock'] ?? 0),
                'metadata' => $variantData['metadata'] ?? [],
                'is_default' => (bool) ($variantData['is_default'] ?? false),
                'is_active' => (bool) ($variantData['is_active'] ?? true),
            ]);

            $processedIds[] = $newVariant->id;
            $synced->push($newVariant);
        }

        $product->variants()
            ->whereNotIn('id', $processedIds)
            ->delete();

        return $synced;
    }

    public function updateStock(ProductVariant $variant, int $newStock): ProductVariant
    {
        $variant->update(['stock' => $newStock]);

        return $variant->refresh();
    }

    public function updateStatus(ProductVariant $variant, bool $isActive): ProductVariant
    {
        $variant->update(['is_active' => $isActive]);

        return $variant->refresh();
    }

    public function findFirstActiveExcept(Product $product, string $excludedVariantId): ?ProductVariant
    {
        return $product->variants()
            ->where('id', '!=', $excludedVariantId)
            ->where('is_active', true)
            ->first();
    }
}
