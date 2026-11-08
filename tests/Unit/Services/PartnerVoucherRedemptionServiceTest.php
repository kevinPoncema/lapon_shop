<?php

use App\Models\Order;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Tenant;
use App\Services\Partners\PartnerVoucherRedemptionService;
use Carbon\Carbon;

test('calculo de estado efectivo y redimibilidad detecta correctamente cuando un voucher ha expirado en el tiempo', function () {
    $service = app(PartnerVoucherRedemptionService::class);

    $tenant = Tenant::factory()->create(['slug' => 'tienda-voucher-unit']);
    $partner = PartnerStore::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    // Voucher vigente
    $activeReward = new OutboundReward([
        'tenant_id' => $tenant->id,
        'partner_store_id' => $partner->id,
        'order_id' => $order->id,
        'code' => 'RWD-AAAA-BBBB-CCCC',
        'status' => 'EMITIDO',
        'expires_at' => now()->addDays(5),
    ]);

    $activeEvaluation = $service->computeEffectiveStatusAndRedeemability($activeReward);
    expect($activeEvaluation['is_redeemable'])->toBeTrue()
        ->and($activeEvaluation['status'])->toBe('EMITIDO');

    // Voucher con fecha en el pasado
    $expiredReward = new OutboundReward([
        'tenant_id' => $tenant->id,
        'partner_store_id' => $partner->id,
        'order_id' => $order->id,
        'code' => 'RWD-XXXX-YYYY-ZZZZ',
        'status' => 'EMITIDO',
        'expires_at' => now()->subMinute(),
    ]);

    $expiredEvaluation = $service->computeEffectiveStatusAndRedeemability($expiredReward);
    expect($expiredEvaluation['is_redeemable'])->toBeFalse()
        ->and($expiredEvaluation['status'])->toBe('EXPIRADO');

    // Voucher ya canjeado
    $redeemedReward = new OutboundReward([
        'tenant_id' => $tenant->id,
        'partner_store_id' => $partner->id,
        'order_id' => $order->id,
        'code' => 'RWD-1111-2222-3333',
        'status' => 'CANJEADO',
        'expires_at' => now()->addDays(5),
        'redeemed_at' => now()->subHour(),
    ]);

    $redeemedEvaluation = $service->computeEffectiveStatusAndRedeemability($redeemedReward);
    expect($redeemedEvaluation['is_redeemable'])->toBeFalse()
        ->and($redeemedEvaluation['status'])->toBe('CANJEADO');
});

test('saneamiento de codigo normaliza cadenas en minusculas y elimina espacios en blanco laterales', function () {
    $service = app(PartnerVoucherRedemptionService::class);

    expect($service->sanitizeVoucherCode('  rwd-9f8a-7c2b-4e1d  '))->toBe('RWD-9F8A-7C2B-4E1D')
        ->and($service->sanitizeVoucherCode('RWD-1234-ABCD-5678'))->toBe('RWD-1234-ABCD-5678');
});

test('proyeccion del DTO de inspeccion salvaguarda la privacidad del cliente y excluye datos sensibles', function () {
    $service = app(PartnerVoucherRedemptionService::class);

    $tenant = Tenant::factory()->create(['name' => 'Tienda Moda']);
    $partner = PartnerStore::factory()->create([
        'tenant_id' => $tenant->id,
        'benefit_title' => '20% off',
        'terms_conditions' => 'Sólo consumo presencial',
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_info' => [
            'full_name' => 'Carlos Mendoza',
            'email' => 'carlos.mendoza@secret.com',
            'whatsapp_phone' => '+50499887766',
        ],
        'shipping_address' => [
            'address_line' => 'Col. Trejo Casa 42',
            'region_code' => 'HN-CR',
        ],
        'total_net' => 1500.00,
    ]);

    $reward = OutboundReward::factory()->create([
        'tenant_id' => $tenant->id,
        'partner_store_id' => $partner->id,
        'order_id' => $order->id,
        'code' => 'RWD-9F8A-7C2B-4E1D',
        'status' => 'EMITIDO',
        'expires_at' => Carbon::parse('2026-12-06 23:59:59'),
    ]);

    $dto = $service->buildInspectionDto($reward);
    $array = $dto->toArray();

    expect($array['customer_name'])->toBe('Carlos Mendoza')
        ->and($array['origin_tenant_name'])->toBe('Tienda Moda')
        ->and($array['voucher_code'])->toBe('RWD-9F8A-7C2B-4E1D')
        ->and($array['is_redeemable'])->toBeTrue()
        ->and(array_key_exists('email', $array))->toBeFalse()
        ->and(array_key_exists('whatsapp_phone', $array))->toBeFalse()
        ->and(array_key_exists('shipping_address', $array))->toBeFalse()
        ->and(array_key_exists('total_net', $array))->toBeFalse();
});
