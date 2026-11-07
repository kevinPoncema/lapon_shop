<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Get the tokenable model that the access token belongs to without tenant scope.
     *
     * @return MorphTo<Model, $this>
     */
    public function tokenable(): MorphTo
    {
        return parent::tokenable()->withoutGlobalScope(TenantScope::class);
    }
}
