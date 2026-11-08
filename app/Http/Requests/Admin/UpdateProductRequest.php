<?php

namespace App\Http\Requests\Admin;

use App\Context\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('variants') && is_array($this->input('variants'))) {
            $variants = $this->input('variants');
            foreach ($variants as &$variant) {
                if (isset($variant['sku']) && is_string($variant['sku'])) {
                    $variant['sku'] = strtoupper(trim($variant['sku']));
                }
            }
            $this->merge(['variants' => $variants]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(TenantContext $tenantContext): array
    {
        $tenantId = $tenantContext->id();
        $productId = $this->route('id') ?? $this->route('product');

        return [
            'category_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('products', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($productId),
            ],
            'description' => ['nullable', 'string'],
            'base_price' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'url', 'max:500'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'variants' => ['sometimes', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'uuid'],
            'variants.*.sku' => ['nullable', 'string', 'max:80'],
            'variants.*.price' => ['nullable', 'numeric', 'gt:0'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.metadata' => ['nullable', 'array'],
            'variants.*.is_default' => ['sometimes', 'boolean'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
