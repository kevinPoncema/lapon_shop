<?php

namespace App\Repositories\Eloquent\Queries;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductFacetedSearchQuery
{
    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $metadataSchema
     * @return Builder<Product>
     */
    public function build(array $criteria, array $metadataSchema): Builder
    {
        $query = $this->createBaseQuery();
        $schemaByKey = collect($metadataSchema)->keyBy('key')->all();
        $inStockOnly = filter_var($criteria['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->applySearchTerm($query, $criteria['q'] ?? null);
        $this->applyCategory($query, $criteria);
        $this->applyPriceRange($query, $criteria);
        $this->applyStockAvailability($query, $inStockOnly);
        $this->applyDynamicAttributes($query, (array) ($criteria['attr'] ?? []), $schemaByKey, $inStockOnly);
        $this->applySorting($query, $criteria['sort'] ?? null);

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    protected function createBaseQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->with([
                'category',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('is_default', 'desc'),
            ]);
    }

    /**
     * @param  Builder<Product>  $query
     */
    protected function applySearchTerm(Builder $query, ?string $term): void
    {
        if (empty($term)) {
            return;
        }

        $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->where(function (Builder $subQuery) use ($term, $operator) {
            $subQuery->where('name', $operator, "%{$term}%")
                ->orWhere('description', $operator, "%{$term}%");
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $criteria
     */
    protected function applyCategory(Builder $query, array $criteria): void
    {
        if (! empty($criteria['category_slug'])) {
            $category = Category::where('slug', (string) $criteria['category_slug'])->first();

            if ($category !== null) {
                $categoryIds = $category->children()->pluck('id')->prepend($category->id)->all();
                $query->whereIn('category_id', $categoryIds);
            }

            return;
        }

        if (! empty($criteria['category_id'])) {
            $query->where('category_id', (string) $criteria['category_id']);
        }
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $criteria
     */
    protected function applyPriceRange(Builder $query, array $criteria): void
    {
        if (isset($criteria['price_min']) && is_numeric($criteria['price_min'])) {
            $query->where('base_price', '>=', (float) $criteria['price_min']);
        }

        if (isset($criteria['price_max']) && is_numeric($criteria['price_max'])) {
            $query->where('base_price', '<=', (float) $criteria['price_max']);
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    protected function applyStockAvailability(Builder $query, bool $inStockOnly): void
    {
        if (! $inStockOnly) {
            return;
        }

        $query->whereHas('variants', function (Builder $variantQuery) {
            $variantQuery->where('is_active', true)
                ->where('stock', '>', 0);
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<string, mixed>>  $schemaByKey
     */
    protected function applyDynamicAttributes(
        Builder $query,
        array $attributes,
        array $schemaByKey,
        bool $inStockOnly
    ): void {
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $this->applySingleAttributeFilter($query, (string) $key, $value, $schemaByKey, $inStockOnly);
        }
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, array<string, mixed>>  $schemaByKey
     */
    protected function applySingleAttributeFilter(
        Builder $query,
        string $key,
        mixed $value,
        array $schemaByKey,
        bool $inStockOnly
    ): void {
        $definition = $schemaByKey[$key] ?? null;
        $affectsStock = $definition !== null
            ? (bool) ($definition['affects_stock'] ?? false)
            : true;

        if ($affectsStock) {
            $query->whereHas('variants', function (Builder $variantQuery) use ($key, $value, $inStockOnly) {
                $variantQuery->where('is_active', true);

                if ($inStockOnly) {
                    $variantQuery->where('stock', '>', 0);
                }

                $variantQuery->whereJsonContains("metadata->{$key}", $value);
            });

            return;
        }

        $query->whereJsonContains("metadata->{$key}", $value);
    }

    /**
     * @param  Builder<Product>  $query
     */
    protected function applySorting(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('base_price', 'asc'),
            'price_desc' => $query->orderBy('base_price', 'desc'),
            default => $query->latest(),
        };
    }
}
