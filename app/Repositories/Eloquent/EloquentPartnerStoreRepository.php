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

    public function findByPortalEmail(string $email): ?PartnerStore
    {
        return PartnerStore::query()
            ->whereRaw('LOWER(portal_email) = ?', [strtolower(trim($email))])
            ->first();
    }

    public function existsByPortalEmail(string $email, ?string $ignoreId = null): bool
    {
        return PartnerStore::query()
            ->whereRaw('LOWER(portal_email) = ?', [strtolower(trim($email))])
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
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

    /**
     * @return Collection<int, PartnerStore>
     */
    public function listWithRewardCounts(): Collection
    {
        return PartnerStore::query()
            ->withCount([
                'outboundRewards as vouchers_count',
                'outboundRewards as redeemed_count' => function ($query) {
                    $query->where('status', 'CANJEADO');
                },
            ])
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PartnerStore
    {
        $attributes['tenant_id'] = $this->tenantContext->id();
        $attributes['portal_email'] = strtolower(trim((string) $attributes['portal_email']));

        return PartnerStore::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PartnerStore $partner, array $attributes): PartnerStore
    {
        if (isset($attributes['portal_email'])) {
            $attributes['portal_email'] = strtolower(trim((string) $attributes['portal_email']));
        }

        $partner->update($attributes);

        return $partner->fresh();
    }
}
