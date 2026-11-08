<?php

namespace App\Services\Partners;

use App\Context\TenantContext;
use App\DTOs\VoucherInspectionResultDTO;
use App\DTOs\VoucherRedemptionResultDTO;
use App\Exceptions\Domain\VoucherAlreadyRedeemedException;
use App\Exceptions\Domain\VoucherExpiredException;
use App\Exceptions\Domain\VoucherNotFoundException;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Repositories\Contracts\OutboundRewardRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PartnerVoucherRedemptionService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected OutboundRewardRepositoryInterface $outboundRewardRepository
    ) {}

    /**
     * Sanea y normaliza el código de cupón (mayúsculas, trim, urldecode).
     */
    public function sanitizeVoucherCode(string $code): string
    {
        return strtoupper(trim(urldecode($code)));
    }

    /**
     * Evalúa el estado efectivo y redimibilidad de una recompensa.
     *
     * @return array{is_redeemable: bool, status: string}
     */
    public function computeEffectiveStatusAndRedeemability(OutboundReward $reward): array
    {
        if ($reward->status === 'CANJEADO' || $reward->redeemed_at !== null) {
            return [
                'is_redeemable' => false,
                'status' => 'CANJEADO',
            ];
        }

        if ($reward->expires_at->isPast()) {
            return [
                'is_redeemable' => false,
                'status' => 'EXPIRADO',
            ];
        }

        if ($reward->status === 'EXPIRADO') {
            return [
                'is_redeemable' => false,
                'status' => 'EXPIRADO',
            ];
        }

        if ($reward->status === 'EMITIDO') {
            return [
                'is_redeemable' => true,
                'status' => 'EMITIDO',
            ];
        }

        return [
            'is_redeemable' => false,
            'status' => $reward->status,
        ];
    }

    /**
     * Proyecta la entidad OutboundReward en el DTO de inspección salvaguardando privacidad.
     */
    public function buildInspectionDto(OutboundReward $reward): VoucherInspectionResultDTO
    {
        $evaluation = $this->computeEffectiveStatusAndRedeemability($reward);

        $order = $reward->order;
        $customerInfo = $order->customer_info;
        $customerName = ! empty($customerInfo['full_name'])
            ? (string) $customerInfo['full_name']
            : 'Cliente';

        $originTenantName = $order->tenant->name;
        $partner = $reward->partnerStore;

        return new VoucherInspectionResultDTO(
            voucherCode: $reward->code,
            status: $evaluation['status'],
            isRedeemable: $evaluation['is_redeemable'],
            benefitTitle: $partner->benefit_title,
            termsConditions: (string) ($partner->terms_conditions ?? ''),
            customerName: $customerName,
            originTenantName: $originTenantName,
            expiresAt: $reward->expires_at->toISOString(),
            redeemedAt: $reward->redeemed_at?->toISOString()
        );
    }

    /**
     * Inspecciona un cupón para el comercio aliado autenticado.
     *
     * @throws VoucherNotFoundException
     */
    public function inspectVoucher(PartnerStore $partner, string $code): VoucherInspectionResultDTO
    {
        $cleanCode = $this->sanitizeVoucherCode($code);
        $reward = $this->outboundRewardRepository->findDetailedByCodeForPartner($partner->id, $cleanCode);

        if (! $reward) {
            throw new VoucherNotFoundException;
        }

        if ($reward->status === 'EMITIDO' && $reward->expires_at->isPast()) {
            $reward = $this->outboundRewardRepository->updateStatus($reward, ['status' => 'EXPIRADO']);
        }

        return $this->buildInspectionDto($reward);
    }

    /**
     * Orquesta el canje concurrente y seguro de un cupón aplicando bloqueo pesimista.
     *
     * @throws VoucherNotFoundException
     * @throws VoucherAlreadyRedeemedException
     * @throws VoucherExpiredException
     */
    public function orchestrateRedemption(PartnerStore $partner, string $code): VoucherRedemptionResultDTO
    {
        $cleanCode = $this->sanitizeVoucherCode($code);

        $expiredReward = null;

        /** @var VoucherRedemptionResultDTO|null $result */
        $result = DB::transaction(function () use ($partner, $cleanCode, &$expiredReward): ?VoucherRedemptionResultDTO {
            $reward = $this->outboundRewardRepository->findByCodeForPartnerWithLock($partner->id, $cleanCode);

            if (! $reward) {
                throw new VoucherNotFoundException;
            }

            if ($reward->status === 'CANJEADO' || $reward->redeemed_at !== null) {
                throw new VoucherAlreadyRedeemedException($reward->redeemed_at);
            }

            if ($reward->expires_at->isPast()) {
                $expiredReward = $reward;

                return null;
            }

            $now = now();
            $updatedReward = $this->outboundRewardRepository->updateStatus($reward, [
                'status' => 'CANJEADO',
                'redeemed_at' => $now,
            ]);

            return new VoucherRedemptionResultDTO(
                voucherCode: $updatedReward->code,
                status: 'CANJEADO',
                redeemedAt: $now->toISOString(),
                message: 'Beneficio canjeado exitosamente.'
            );
        });

        if ($expiredReward !== null) {
            $this->outboundRewardRepository->updateStatus($expiredReward, ['status' => 'EXPIRADO']);
            throw new VoucherExpiredException($expiredReward->expires_at);
        }

        /** @var VoucherRedemptionResultDTO $result */
        return $result;
    }
}
