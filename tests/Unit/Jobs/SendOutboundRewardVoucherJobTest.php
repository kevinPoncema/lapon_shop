<?php

use App\Context\TenantContext;
use App\Jobs\SendOutboundRewardVoucherJob;
use App\Mail\OutboundRewardVoucherMail;
use App\Models\Order;
use App\Models\OutboundReward;
use App\Models\PartnerStore;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tienda-voucher-job',
        'is_active' => true,
    ]);

    app(TenantContext::class)->set($this->tenant);

    $this->partnerStore = PartnerStore::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cafetería La Selva',
        'benefit_title' => 'Café gratis en tu próxima visita',
        'terms_conditions' => 'Válido en cualquier sucursal.',
        'is_active' => true,
    ]);

    $this->order = Order::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_number' => '#ORD-VOUCH1',
        'status' => 'COMPLETADA',
        'customer_info' => [
            'full_name' => 'Ana Gómez',
            'email' => 'ana.gomez@example.com',
            'whatsapp_phone' => '+50499112233',
        ],
    ]);

    $this->reward = OutboundReward::factory()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => $this->order->id,
        'partner_store_id' => $this->partnerStore->id,
        'code' => 'RWD-A1B2-C3D4-E5F6',
        'status' => 'EMITIDO',
        'expires_at' => now()->addDays(30),
        'email_sent_at' => null,
    ]);
});

test('despacha correo con plantilla y codigo al comprador y actualiza email_sent_at', function () {
    Mail::fake();

    $job = new SendOutboundRewardVoucherJob($this->reward->id, $this->tenant->id);
    $job->handle(app(TenantContext::class));

    Mail::assertSent(OutboundRewardVoucherMail::class, function (OutboundRewardVoucherMail $mail) {
        return $mail->hasTo('ana.gomez@example.com')
            && $mail->reward->id === $this->reward->id;
    });

    $freshReward = $this->reward->fresh();
    expect($freshReward->email_sent_at)->not->toBeNull();
});

test('es idempotente y no reenvia correo si email_sent_at ya tiene marca temporal o si el estado no es EMITIDO', function () {
    Mail::fake();

    $this->reward->update(['email_sent_at' => now()->subDay()]);

    $job = new SendOutboundRewardVoucherJob($this->reward->id, $this->tenant->id);
    $job->handle(app(TenantContext::class));

    Mail::assertNothingSent();

    // Caso cuando ya está CANJEADO
    $this->reward->update([
        'email_sent_at' => null,
        'status' => 'CANJEADO',
    ]);

    $job2 = new SendOutboundRewardVoucherJob($this->reward->id, $this->tenant->id);
    $job2->handle(app(TenantContext::class));

    Mail::assertNothingSent();
});
