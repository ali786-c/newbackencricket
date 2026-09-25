<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class FindDuplicateCandidatesRequest extends ApiFormRequest
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
        return [
            'entityType' => ['required', Rule::in(['player', 'team'])],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'city' => ['required', 'string', 'min:1', 'max:100'],
            'excludeId' => ['sometimes', 'string', 'ulid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'entityType' => $this->route('entityType'),
            'name' => $this->string('name')->squish()->toString(),
            'city' => $this->string('city')->squish()->toString(),
        ]);
    }
}
