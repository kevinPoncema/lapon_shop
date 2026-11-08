<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function findByIdWithVariants(string $id): ?Product;

    public function findActiveBySlugForStorefront(string $slug): ?Product;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateForAdmin(int $perPage = 20, array $filters = []): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $metadataSchema
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchFacetedForStorefront(array $criteria, array $metadataSchema, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product;
}
