<?php

namespace App\Http\Requests\Admin;

use App\Context\TenantContext;
use App\Services\Tenant\TenantConfigService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCatalogSchemaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(TenantContext $tenantContext): bool
    {
        $user = $this->user();

        return $user !== null
            && $tenantContext->isSet()
            && $user->tenant_id === $tenantContext->id()
            && (bool) $user->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'metadata_schema' => ['sometimes', 'array'],
            'metadata_schema.*.key' => [
                'required',
                'string',
                'max:64',
                'distinct',
                'regex:/^[a-z][a-z0-9_]*$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && in_array(Str::lower($value), TenantConfigService::RESERVED_KEYWORDS, true)) {
                        $fail("La clave {$value} es una palabra reservada del sistema y no puede ser utilizada como metadato dinámico.");
                    }
                },
            ],
            'metadata_schema.*.label' => ['required', 'string', 'max:120'],
            'metadata_schema.*.data_type' => [
                'required',
                'string',
                Rule::in(TenantConfigService::ALLOWED_DATA_TYPES),
            ],
            'metadata_schema.*.options' => [
                'required_if:metadata_schema.*.data_type,select,multiselect',
                'nullable',
                'array',
                'min:1',
            ],
            'metadata_schema.*.options.*' => ['string'],
            'metadata_schema.*.is_required' => ['sometimes', 'boolean'],
            'metadata_schema.*.affects_stock' => ['sometimes', 'boolean'],

            'filter_config' => ['sometimes', 'array'],
            'filter_config.*.type' => ['required', 'string', Rule::in(['system', 'metadata'])],
            'filter_config.*.key' => ['required', 'string', 'max:64'],
            'filter_config.*.label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filter_config.*.data_type' => ['sometimes', 'nullable', 'string', 'max:30'],
            'filter_config.*.order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
