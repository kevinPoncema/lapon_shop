<?php

use App\Services\Orders\OrderLifecycleService;
use Carbon\Carbon;

test('matriz de transiciones permite exclusivamente transiciones desde PENDIENTE_PAGO hacia COMPLETADA o CANCELADA', function () {
    $service = app(OrderLifecycleService::class);

    // Válidas
    expect($service->canTransition('PENDIENTE_PAGO', 'COMPLETADA'))->toBeTrue()
        ->and($service->canTransition('PENDIENTE_PAGO', 'CANCELADA'))->toBeTrue();

    // Inválidas
    expect($service->canTransition('COMPLETADA', 'CANCELADA'))->toBeFalse()
        ->and($service->canTransition('CANCELADA', 'COMPLETADA'))->toBeFalse()
        ->and($service->canTransition('COMPLETADA', 'COMPLETADA'))->toBeFalse()
        ->and($service->canTransition('CANCELADA', 'CANCELADA'))->toBeFalse()
        ->and($service->canTransition('PENDIENTE_PAGO', 'PENDIENTE_PAGO'))->toBeFalse()
        ->and($service->canTransition('PENDIENTE_PAGO', 'OTRO_ESTADO'))->toBeFalse();
});

test('evaluador de reglas outbound_reward_rules valida umbrales de monto minimo cantidad de items y habilitacion', function () {
    $service = app(OrderLifecycleService::class);

    $rulesDisabled = [
        'is_enabled' => false,
        'min_order_amount' => 500.00,
        'min_items' => 2,
    ];
    expect($service->isOrderEligibleForOutboundReward($rulesDisabled, 1000.00, 3))->toBeFalse();

    $rulesWithAmount = [
        'is_enabled' => true,
        'min_order_amount' => 500.00,
    ];
    expect($service->isOrderEligibleForOutboundReward($rulesWithAmount, 499.99, 1))->toBeFalse()
        ->and($service->isOrderEligibleForOutboundReward($rulesWithAmount, 500.00, 1))->toBeTrue()
        ->and($service->isOrderEligibleForOutboundReward($rulesWithAmount, 600.00, 1))->toBeTrue();

    $rulesWithItems = [
        'is_enabled' => true,
        'min_items' => 3,
    ];
    expect($service->isOrderEligibleForOutboundReward($rulesWithItems, 100.00, 2))->toBeFalse()
        ->and($service->isOrderEligibleForOutboundReward($rulesWithItems, 100.00, 3))->toBeTrue();

    // Umbral mixto: si ambos están definidos, ambos deben cumplirse
    $rulesBoth = [
        'is_enabled' => true,
        'min_order_amount' => 500.00,
        'min_items' => 2,
    ];
    expect($service->isOrderEligibleForOutboundReward($rulesBoth, 600.00, 1))->toBeFalse()
        ->and($service->isOrderEligibleForOutboundReward($rulesBoth, 400.00, 3))->toBeFalse()
        ->and($service->isOrderEligibleForOutboundReward($rulesBoth, 500.00, 2))->toBeTrue();
});

test('calculo de expiracion de voucher suma validity_days configurados o recurre al default de 30 dias', function () {
    $service = app(OrderLifecycleService::class);
    $now = Carbon::parse('2026-11-06 12:00:00');

    $rulesDefault = ['is_enabled' => true];
    $expirationDefault = $service->calculateRewardExpiration($rulesDefault, $now);
    expect($expirationDefault->toIso8601String())->toBe(Carbon::parse('2026-12-06 12:00:00')->toIso8601String());

    $rulesCustom = ['is_enabled' => true, 'validity_days' => 15];
    $expirationCustom = $service->calculateRewardExpiration($rulesCustom, $now);
    expect($expirationCustom->toIso8601String())->toBe(Carbon::parse('2026-11-21 12:00:00')->toIso8601String());
});

test('generador de codigos criptograficos produce formato RWD-XXXX-XXXX-XXXX con alta entropia y mayusculas', function () {
    $service = app(OrderLifecycleService::class);

    $code = $service->generateCryptographicVoucherCode();

    expect($code)->toMatch('/^RWD-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');

    // Unicidad y entropía en múltiples generaciones sucesivas
    $codes = collect(range(1, 50))->map(fn () => $service->generateCryptographicVoucherCode());
    expect($codes->unique()->count())->toBe(50);
});
