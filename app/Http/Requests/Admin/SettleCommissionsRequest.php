<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettleCommissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'commission_ids' => ['required', 'array', 'min:1'],
            'commission_ids.*' => ['required', 'uuid', 'distinct'],
            'payment_reference' => ['required', 'string', 'max:120'],
            'payment_receipt_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    public function commissionIds(): array
    {
        /** @var list<string> $ids */
        $ids = array_values((array) $this->input('commission_ids', []));

        return $ids;
    }
}
