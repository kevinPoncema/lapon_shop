<?php

namespace App\Http\Requests\Admin;

use App\Context\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(TenantContext $tenantContext): array
    {
        $tenantId = $tenantContext->id();
        $categoryId = $this->route('id') ?? $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('categories', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($categoryId),
            ],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
