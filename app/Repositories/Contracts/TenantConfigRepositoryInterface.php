<?php

namespace App\Repositories\Contracts;

use App\Models\TenantConfig;

interface TenantConfigRepositoryInterface
{
    /**
     * Find a tenant config by tenant UUID.
     */
    public function findByTenantId(string $tenantId): ?TenantConfig;

    /**
     * Get existing tenant config or create default entry.
     */
    public function getOrCreateByTenantId(string $tenantId): TenantConfig;

    /**
     * Update dynamic metadata schema and public filter configuration.
     *
     * @param  list<array<string, mixed>>  $metadataSchema
     * @param  list<array<string, mixed>>  $filterConfig
     */
    public function updateCatalogSchema(string $tenantId, array $metadataSchema, array $filterConfig): TenantConfig;

    /**
     * Update branding visual identity.
     *
     * @param  array<string, mixed>  $brandingData
     */
    public function updateBranding(string $tenantId, array $brandingData): TenantConfig;

    /**
     * Update shipping zones and dispatch fees.
     *
     * @param  list<array<string, mixed>>  $shippingZones
     */
    public function updateShippingZones(string $tenantId, array $shippingZones): TenantConfig;
}
