<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkGroup\Pages;

use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\Support\VkUrl;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;

/**
 * @extends FormPage<VkGroupResource>
 */
class VkGroupFormPage extends FormPage
{
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'string',
                'max:255',
                'url',
                Rule::unique('vk_groups', 'url')->ignore($item->getKey()),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! VkUrl::isValid(is_string($value) ? $value : null)) {
                        $fail(VkUrl::validationMessage());
                    }
                },
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
