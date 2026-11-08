<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactInfoRequest extends FormRequest
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
            'whatsapp_sales' => ['nullable', 'string', 'max:50'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank_name' => ['required_with:bank_accounts', 'string', 'max:100'],
            'bank_accounts.*.account_number' => ['required_with:bank_accounts', 'string', 'max:100'],
            'bank_accounts.*.account_type' => ['required_with:bank_accounts', 'string', 'max:50'],
            'bank_accounts.*.holder_name' => ['required_with:bank_accounts', 'string', 'max:150'],
        ];
    }
}
