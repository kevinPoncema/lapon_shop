<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\Affiliate;
use App\Repositories\Contracts\AffiliateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentAffiliateRepository implements AffiliateRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function findById(string $id): ?Affiliate
    {
        return Affiliate::query()->find($id);
    }

    public function existsByEmail(string $email, ?string $ignoreId = null): bool
    {
        return Affiliate::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * @return Collection<int, Affiliate>
     */
    public function listForAdmin(): Collection
    {
        return Affiliate::query()
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Affiliate
    {
        $data['tenant_id'] = $this->tenantContext->id();
        $data['email'] = strtolower(trim((string) $data['email']));

        return Affiliate::query()->create($data);
    }
}
