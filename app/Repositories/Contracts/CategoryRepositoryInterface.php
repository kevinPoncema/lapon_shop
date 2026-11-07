<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    public function findById(string $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    /**
     * @return Collection<int, Category>
     */
    public function getTreeForAdmin(): Collection;

    /**
     * @return Collection<int, Category>
     */
    public function getPublicTreeWithActiveProducts(): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category;
}
