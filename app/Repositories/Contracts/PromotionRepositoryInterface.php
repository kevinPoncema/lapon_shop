<?php

namespace App\Repositories\Contracts;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

interface PromotionRepositoryInterface
{
    public function findById(string $id): ?Promotion;

    /**
     * @return Collection<int, Promotion>
     */
    public function getActiveAutomaticForTenant(): Collection;

    /**
     * @return Collection<int, Promotion>
     */
    public function listForAdmin(): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Promotion;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promotion $promotion, array $data): Promotion;
}
