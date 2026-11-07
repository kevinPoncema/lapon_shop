<?php

namespace App\Context;

use App\Models\Tenant;
use RuntimeException;

class TenantContext
{
    private ?Tenant $currentTenant = null;

    /**
     * Set the currently active tenant.
     */
    public function set(Tenant $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    /**
     * Get the currently active tenant.
     *
     * @throws RuntimeException If no tenant is set.
     */
    public function get(): Tenant
    {
        if ($this->currentTenant === null) {
            throw new RuntimeException('Operación abortada: No se ha establecido un inquilino activo en TenantContext.');
        }

        return $this->currentTenant;
    }

    /**
     * Get the active tenant's UUID identifier.
     */
    public function id(): string
    {
        return $this->get()->id;
    }

    /**
     * Check if an active tenant is set.
     */
    public function isSet(): bool
    {
        return $this->currentTenant !== null;
    }

    /**
     * Clear the active tenant context.
     */
    public function clear(): void
    {
        $this->currentTenant = null;
    }

    /**
     * Execute a callback in the context of the given tenant, safely restoring the previous state.
     *
     * @template T
     *
     * @param  callable(Tenant): T  $callback
     * @return T
     */
    public function executeAs(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->currentTenant;
        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->currentTenant = $previous;
        }
    }
}
