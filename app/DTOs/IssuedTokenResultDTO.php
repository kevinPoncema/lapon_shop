<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class IssuedTokenResultDTO implements Arrayable
{
    /**
     * @param  list<string>  $abilities
     * @param  array<string, mixed>|null  $user
     * @param  array<string, mixed>|null  $tenant
     * @param  array<string, mixed>|null  $partnerStore
     * @param  array<string, mixed>|null  $originTenant
     */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public array $abilities,
        public string $expiresAt,
        public ?array $user = null,
        public ?array $tenant = null,
        public ?array $partnerStore = null,
        public ?array $originTenant = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'abilities' => $this->abilities,
            'expires_at' => $this->expiresAt,
        ];

        if ($this->user !== null) {
            $data['user'] = $this->user;
        }

        if ($this->tenant !== null) {
            $data['tenant'] = $this->tenant;
        }

        if ($this->partnerStore !== null) {
            $data['partner_store'] = $this->partnerStore;
        }

        if ($this->originTenant !== null) {
            $data['origin_tenant'] = $this->originTenant;
        }

        return $data;
    }
}
