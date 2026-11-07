<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\Queries\ProductFacetedSearchQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findByIdWithVariants(string $id): ?Product
    {
        return Product::query()
            ->withCount('variants')
            ->withSum('variants as total_stock', 'stock')
            ->with(['category', 'variants' => fn ($query) => $query->orderBy('is_default', 'desc')])
            ->find($id);
    }

    public function findActiveBySlugForStorefront(string $slug): ?Product
    {
        return Product::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_active', true)->orderBy('is_default', 'desc'),
            ])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateForAdmin(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return Product::query()
            ->withCount('variants')
            ->withSum('variants as total_stock', 'stock')
            ->with(['category', 'variants' => fn ($query) => $query->orderBy('is_default', 'desc')])
            ->when($search !== null && $search !== '', function (Builder $query) use ($search, $operator) {
                $query->where(function (Builder $sub) use ($search, $operator) {
                    $sub->where('name', $operator, "%{$search}%")
                        ->orWhere('description', $operator, "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, $catId) => $query->where('category_id', $catId))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $metadataSchema
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchFacetedForStorefront(array $criteria, array $metadataSchema, int $perPage = 20): LengthAwarePaginator
    {
        return (new ProductFacetedSearchQuery)
            ->build($criteria, $metadataSchema)
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return Product::create([
            'tenant_id' => $this->tenantContext->id(),
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'],
            'metadata' => $data['metadata'] ?? [],
            'images' => $data['images'] ?? [],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update(array_filter([
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $product->category_id,
            'name' => $data['name'] ?? $product->name,
            'slug' => $data['slug'] ?? $product->slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
            'base_price' => $data['base_price'] ?? $product->base_price,
            'metadata' => array_key_exists('metadata', $data) ? $data['metadata'] : $product->metadata,
            'images' => array_key_exists('images', $data) ? $data['images'] : $product->images,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $product->is_active,
        ], fn ($val) => $val !== null));

        return $product->refresh();
    }
}
