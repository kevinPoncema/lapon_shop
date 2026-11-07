<?php

namespace App\Repositories\Contracts;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Collection;

interface AffiliateRepositoryInterface
{
    public function findById(string $id): ?Affiliate;

    public function existsByEmail(string $email, ?string $ignoreId = null): bool;

    /**
     * @return Collection<int, Affiliate>
     */
    public function listForAdmin(): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Affiliate;
}
