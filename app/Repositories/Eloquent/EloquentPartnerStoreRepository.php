<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\PartnerStore;
use App\Repositories\Contracts\PartnerStoreRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentPartnerStoreRepository implements PartnerStoreRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findById(string $id): ?PartnerStore
    {
        return PartnerStore::query()->find($id);
    }

    public function findActiveById(string $id): ?PartnerStore
    {
        return PartnerStore::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    public function findFirstActiveForTenant(): ?PartnerStore
    {
        return PartnerStore::query()
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return Collection<int, PartnerStore>
     */
    public function listActiveForTenant(): Collection
    {
        return PartnerStore::query()
            ->where('is_active', true)
            ->get();
    }
}
