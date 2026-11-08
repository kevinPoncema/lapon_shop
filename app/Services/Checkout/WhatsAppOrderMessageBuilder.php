<?php

namespace App\Services\Checkout;

use App\Models\Tenant;
use App\Models\TenantConfig;

class WhatsAppOrderMessageBuilder
{
    /**
     * @param  array<string, mixed>  $customerInfo
     * @param  array<string, mixed>  $shippingAddress
     * @param  array<string, mixed>  $pricing
     */
    public function buildRedirectUrl(
        Tenant $tenant,
        string $orderNumber,
        array $customerInfo,
        array $shippingAddress,
        array $pricing,
        ?TenantConfig $config
    ): string {
        $phoneDigits = $this->resolveDestinationPhone($tenant, $config);
        $message = $this->buildMessageText($orderNumber, $customerInfo, $shippingAddress, $pricing, $config);

        return 'https://wa.me/'.$phoneDigits.'?text='.rawurlencode($message);
    }

    protected function resolveDestinationPhone(Tenant $tenant, ?TenantConfig $config): string
    {
        $contact = $config ? $config->contact_info : [];
        $rawPhone = $contact['whatsapp_sales'] ?? ($contact['whatsapp'] ?? ($contact['phone'] ?? '+50499000000'));
        $digits = preg_replace('/[^0-9]/', '', (string) $rawPhone);

        return ! empty($digits) ? $digits : '50499000000';
    }

    /**
     * @param  array<string, mixed>  $customerInfo
     * @param  array<string, mixed>  $shippingAddress
     * @param  array<string, mixed>  $pricing
     */
    protected function buildMessageText(
        string $orderNumber,
        array $customerInfo,
        array $shippingAddress,
        array $pricing,
        ?TenantConfig $config
    ): string {
        $lines = [];
        $lines[] = "*¡NUEVO PEDIDO {$orderNumber}!*";
        $lines[] = '';
        $lines[] = '*Cliente:* '.($customerInfo['full_name'] ?? '');
        $lines[] = '*Teléfono:* '.($customerInfo['whatsapp_phone'] ?? '');

        $region = $pricing['shipping_zone_name'] ?? ($shippingAddress['region_code'] ?? '');
        $address = ($shippingAddress['address_line'] ?? '').($region ? " ({$region})" : '');
        $lines[] = "*Dirección:* {$address}";

        if (! empty($shippingAddress['reference'])) {
            $lines[] = "*Referencia:* {$shippingAddress['reference']}";
        }

        $lines[] = '';
        $lines[] = '*Detalle de Productos:*';

        $items = is_array($pricing['items'] ?? null) ? $pricing['items'] : [];
        foreach ($items as $item) {
            $qty = $item['quantity'] ?? 1;
            $name = $item['product_name'] ?? 'Producto';
            $attrs = $this->formatAttributes($item['variant_attributes'] ?? []);
            $attrStr = $attrs ? " ({$attrs})" : '';
            $lineTotal = number_format((float) ($item['line_total'] ?? 0), 2);
            $lines[] = "• {$qty}x {$name}{$attrStr} - L. {$lineTotal}";
        }

        $lines[] = '';
        $lines[] = '*Subtotal:* L. '.number_format((float) ($pricing['subtotal'] ?? 0), 2);

        if (! empty($pricing['auto_discount_total']) && (float) $pricing['auto_discount_total'] > 0) {
            $lines[] = '*Descuento Automático:* -L. '.number_format((float) $pricing['auto_discount_total'], 2);
        }

        if (! empty($pricing['coupon_discount_total']) && (float) $pricing['coupon_discount_total'] > 0) {
            $couponCode = $pricing['coupon_code'] ?? '';
            $couponLabel = $couponCode ? " ({$couponCode})" : '';
            $lines[] = "*Descuento Cupón{$couponLabel}:* -L. ".number_format((float) $pricing['coupon_discount_total'], 2);
        }

        $shippingFee = (float) ($pricing['shipping_fee'] ?? 0);
        $lines[] = "*Envío ({$region}):* L. ".number_format($shippingFee, 2);
        $lines[] = '*TOTAL A PAGAR:* L. '.number_format((float) ($pricing['total_net'] ?? 0), 2);

        $bankAccounts = $config?->contact_info['bank_accounts'] ?? [];
        if (! empty($bankAccounts) && is_array($bankAccounts)) {
            $lines[] = '';
            $lines[] = '*Datos para Transferencia Bancaria:*';
            foreach ($bankAccounts as $account) {
                if (is_array($account)) {
                    $bank = $account['bank'] ?? '';
                    $accNum = $account['account'] ?? '';
                    $accType = ! empty($account['type']) ? " ({$account['type']})" : '';
                    $holder = ! empty($account['holder']) ? "A nombre de: {$account['holder']}" : '';
                    $lines[] = "{$bank}: {$accNum}{$accType}";
                    if ($holder) {
                        $lines[] = $holder;
                    }
                }
            }
        }

        $lines[] = '';
        $lines[] = '_Por favor adjunte el comprobante de pago respondiendo a este mensaje._';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function formatAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $val) {
            $parts[] = ucfirst((string) $key).': '.$val;
        }

        return implode(', ', $parts);
    }
}
