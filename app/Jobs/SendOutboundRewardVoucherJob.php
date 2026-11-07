<?php

namespace App\Jobs;

use App\Context\TenantContext;
use App\Mail\OutboundRewardVoucherMail;
use App\Models\OutboundReward;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOutboundRewardVoucherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $outboundRewardId,
        public string $tenantId
    ) {}

    public function handle(TenantContext $tenantContext): void
    {
        if (! $tenantContext->isSet() || $tenantContext->id() !== $this->tenantId) {
            $tenant = Tenant::query()->find($this->tenantId);
            if ($tenant) {
                $tenantContext->set($tenant);
            }
        }

        $reward = OutboundReward::query()
            ->with(['order', 'partnerStore'])
            ->find($this->outboundRewardId);

        if (! $reward) {
            return;
        }

        // Idempotencia: solo enviar si sigue en EMITIDO y no se ha enviado antes
        if ($reward->status !== 'EMITIDO' || $reward->email_sent_at !== null) {
            return;
        }

        $recipientEmail = $reward->order->customer_info['email'] ?? null;
        if (! $recipientEmail || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Mail::to($recipientEmail)->send(new OutboundRewardVoucherMail($reward));

        $reward->update([
            'email_sent_at' => now(),
        ]);
    }
}
