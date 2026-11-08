<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class VoucherRedemptionResultDTO implements Arrayable
{
    public function __construct(
        public string $voucherCode,
        public string $status,
        public string $redeemedAt,
        public string $message = 'Beneficio canjeado exitosamente.'
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'voucher_code' => $this->voucherCode,
            'status' => $this->status,
            'redeemed_at' => $this->redeemedAt,
            'message' => $this->message,
        ];
    }
}
