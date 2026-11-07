<?php

namespace App\Mail;

use App\Models\OutboundReward;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutboundRewardVoucherMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OutboundReward $reward
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Tu Cupón de Regalo Aliado! - Código '.$this->reward->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: sprintf(
                '<h1>¡Felicidades por tu compra!</h1><p>Has recibido un beneficio exclusivo en <strong>%s</strong>: %s</p><p>Tu código de cupón: <strong>%s</strong></p><p>Válido hasta: %s</p><p>Términos y condiciones: %s</p>',
                e($this->reward->partnerStore->name ?? 'Comercio Aliado'),
                e($this->reward->partnerStore->benefit_title ?? 'Beneficio'),
                e($this->reward->code),
                e($this->reward->expires_at->format('Y-m-d')),
                e($this->reward->partnerStore->terms_conditions ?? '')
            ),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
