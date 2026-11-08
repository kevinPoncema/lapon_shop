<?php

namespace App\Repositories\Contracts;

use App\Models\PartnerStore;
use Illuminate\Database\Eloquent\Collection;

interface PartnerStoreRepositoryInterface
{
    public function findById(string $id): ?PartnerStore;

    public function findActiveById(string $id): ?PartnerStore;

    public function findByPortalEmail(string $email): ?PartnerStore;

    public function existsByPortalEmail(string $email, ?string $ignoreId = null): bool;

    public function findFirstActiveForTenant(): ?PartnerStore;

    /**
     * @return Collection<int, PartnerStore>
     */
    public function listActiveForTenant(): Collection;

    /**
     * @return Collection<int, PartnerStore>
     */
    public function listWithRewardCounts(): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PartnerStore;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PartnerStore $partner, array $attributes): PartnerStore;
}
