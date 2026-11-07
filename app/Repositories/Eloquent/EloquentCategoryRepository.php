<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findById(string $id): ?Category
    {
        return Category::query()->find($id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::query()->where('slug', $slug)->first();
    }

    /**
     * @return Collection<int, Category>
     */
    public function getTreeForAdmin(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function getPublicTreeWithActiveProducts(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->where(function (Builder $query) {
                $query->whereHas('products', fn (Builder $q) => $q->where('is_active', true))
                    ->orWhereHas('children', function (Builder $q) {
                        $q->where('is_active', true)
                            ->whereHas('products', fn (Builder $sub) => $sub->where('is_active', true));
                    });
            })
            ->with([
                'children' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('products', fn (Builder $sub) => $sub->where('is_active', true))
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }
}
