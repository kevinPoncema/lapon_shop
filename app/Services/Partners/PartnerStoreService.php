<?php

namespace App\Services\Partners;

use App\Context\TenantContext;
use App\Models\PartnerStore;
use App\Models\Tenant;
use App\Repositories\Contracts\PartnerStoreRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PartnerStoreService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected PartnerStoreRepositoryInterface $partnerStoreRepository
    ) {}

    /**
     * List all partner stores for the current tenant with reward metrics.
     *
     * @return Collection<int, PartnerStore>
     */
    public function listPartnerStoresWithMetrics(): Collection
    {
        return $this->partnerStoreRepository->listWithRewardCounts();
    }

    /**
     * Orchestrate creation of a partner store.
     *
     * @param  array<string, mixed>  $payload
     */
    public function orchestratePartnerCreation(Tenant $tenant, array $payload): PartnerStore
    {
        $portalEmail = strtolower(trim((string) ($payload['portal_email'] ?? '')));

        if ($this->partnerStoreRepository->existsByPortalEmail($portalEmail)) {
            throw ValidationException::withMessages([
                'portal_email' => 'Ya existe un comercio aliado registrado con este correo en esta tienda.',
            ]);
        }

        $payload['tenant_id'] = $tenant->id;
        $payload['portal_email'] = $portalEmail;
        $payload['portal_password'] = Hash::make((string) $payload['portal_password']);

        return $this->partnerStoreRepository->create($payload);
    }

    /**
     * Orchestrate updating an existing partner store.
     *
     * @param  array<string, mixed>  $payload
     */
    public function orchestratePartnerUpdate(Tenant $tenant, string $partnerId, array $payload): PartnerStore
    {
        $partner = $this->partnerStoreRepository->findById($partnerId);

        if (! $partner || $partner->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(PartnerStore::class, [$partnerId]);
        }

        if (isset($payload['portal_email'])) {
            $portalEmail = strtolower(trim((string) $payload['portal_email']));
            $payload['portal_email'] = $portalEmail;

            if ($this->partnerStoreRepository->existsByPortalEmail($portalEmail, $partner->id)) {
                throw ValidationException::withMessages([
                    'portal_email' => 'Ya existe un comercio aliado registrado con este correo en esta tienda.',
                ]);
            }
        }

        $passwordRotated = false;
        if (! empty($payload['portal_password'])) {
            $payload['portal_password'] = Hash::make((string) $payload['portal_password']);
            $passwordRotated = true;
        } else {
            unset($payload['portal_password']);
        }

        $updatedPartner = $this->partnerStoreRepository->update($partner, $payload);

        $wasDeactivated = isset($payload['is_active']) && ! (bool) $payload['is_active'];
        if ($wasDeactivated || $passwordRotated) {
            $this->revokePartnerTokens($updatedPartner);
        }

        return $updatedPartner;
    }

    /**
     * Revoke all active Sanctum tokens for the partner store.
     */
    public function revokePartnerTokens(PartnerStore $partner): void
    {
        $partner->tokens()->delete();
    }
}
