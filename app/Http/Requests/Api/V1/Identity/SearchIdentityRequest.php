<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SearchIdentityRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entityPrefix = $this->routeIs('api.v1.players.index') ? 'P' : 'T';

        return [
            'code' => ['nullable', 'string', "regex:/^STP-{$entityPrefix}-[0-9A-HJKMNP-TV-Z]{8}$/"],
            'query' => ['nullable', 'string', 'min:2', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:500'],
            'scope' => ['nullable', Rule::in(['mine'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? $this->string('code')->trim()->upper()->toString() : null,
            'query' => $this->filled('query') ? $this->string('query')->squish()->lower()->toString() : null,
        ]);
    }
}
