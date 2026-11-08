<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerStoreRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'portal_email' => ['sometimes', 'required', 'email', 'max:150'],
            'portal_password' => ['nullable', 'string', 'min:6'],
            'benefit_title' => ['sometimes', 'required', 'string', 'max:255'],
            'terms_conditions' => ['nullable', 'string'],
            'contact_info' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
