<?php

namespace App\Repositories\Eloquent;

use App\Context\TenantContext;
use App\Models\TenantConfig;
use App\Repositories\Contracts\TenantConfigRepositoryInterface;

class EloquentTenantConfigRepository implements TenantConfigRepositoryInterface
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Find a tenant config by tenant UUID.
     */
    public function findByTenantId(string $tenantId): ?TenantConfig
    {
        return TenantConfig::query()
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Get existing tenant config or create default entry.
     */
    public function getOrCreateByTenantId(string $tenantId): TenantConfig
    {
        /** @var TenantConfig $config */
        $config = TenantConfig::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'branding' => [],
                'contact_info' => [],
                'shipping_zones' => [],
                'metadata_schema' => [],
                'filter_config' => [],
                'outbound_reward_rules' => [],
            ]
        );

        return $config;
    }

    /**
     * Update dynamic metadata schema and public filter configuration.
     *
     * @param  list<array<string, mixed>>  $metadataSchema
     * @param  list<array<string, mixed>>  $filterConfig
     */
    public function updateCatalogSchema(string $tenantId, array $metadataSchema, array $filterConfig): TenantConfig
    {
        $config = $this->getOrCreateByTenantId($tenantId);

        $config->update([
            'metadata_schema' => $metadataSchema,
            'filter_config' => $filterConfig,
        ]);

        return $config->refresh();
    }

    /**
     * Update branding visual identity.
     *
     * @param  array<string, mixed>  $brandingData
     */
    public function updateBranding(string $tenantId, array $brandingData): TenantConfig
    {
        $config = $this->getOrCreateByTenantId($tenantId);

        $currentBranding = $config->branding ?? [];
        $mergedBranding = array_merge($currentBranding, $brandingData);

        $config->update([
            'branding' => $mergedBranding,
        ]);

        return $config->refresh();
    }

    /**
     * Update shipping zones and dispatch fees.
     *
     * @param  list<array<string, mixed>>  $shippingZones
     */
    public function updateShippingZones(string $tenantId, array $shippingZones): TenantConfig
    {
        $config = $this->getOrCreateByTenantId($tenantId);

        $config->update([
            'shipping_zones' => $shippingZones,
        ]);

        return $config->refresh();
    }

    /**
     * Update contact info and bank accounts.
     *
     * @param  array<string, mixed>  $contactInfo
     */
    public function updateContactInfo(string $tenantId, array $contactInfo): TenantConfig
    {
        $config = $this->getOrCreateByTenantId($tenantId);

        $currentContact = $config->contact_info ?? [];
        $mergedContact = array_merge($currentContact, $contactInfo);

        $config->update([
            'contact_info' => $mergedContact,
        ]);

        return $config->refresh();
    }

    /**
     * Update outbound reward rules for partner stores.
     *
     * @param  array<string, mixed>  $outboundRewardRules
     */
    public function updateOutboundRewardRules(string $tenantId, array $outboundRewardRules): TenantConfig
    {
        $config = $this->getOrCreateByTenantId($tenantId);

        $config->update([
            'outbound_reward_rules' => $outboundRewardRules,
        ]);

        return $config->refresh();
    }
}
