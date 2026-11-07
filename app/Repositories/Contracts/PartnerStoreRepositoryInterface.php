<?php

namespace App\Repositories\Contracts;

use App\Models\PartnerStore;
use Illuminate\Database\Eloquent\Collection;

interface PartnerStoreRepositoryInterface
{
    public function findById(string $id): ?PartnerStore;

    public function findActiveById(string $id): ?PartnerStore;

    public function findFirstActiveForTenant(): ?PartnerStore;

    /**
     * @return Collection<int, PartnerStore>
     */
    public function listActiveForTenant(): Collection;
}
