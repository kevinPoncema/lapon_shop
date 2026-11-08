<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class VoucherInspectionResultDTO implements Arrayable
{
    public function __construct(
        public string $voucherCode,
        public string $status,
        public bool $isRedeemable,
        public string $benefitTitle,
        public string $termsConditions,
        public string $customerName,
        public string $originTenantName,
        public string $expiresAt,
        public ?string $redeemedAt = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'voucher_code' => $this->voucherCode,
            'status' => $this->status,
            'is_redeemable' => $this->isRedeemable,
            'benefit_title' => $this->benefitTitle,
            'terms_conditions' => $this->termsConditions,
            'customer_name' => $this->customerName,
            'origin_tenant_name' => $this->originTenantName,
            'expires_at' => $this->expiresAt,
            'redeemed_at' => $this->redeemedAt,
        ];
    }
}
