<?php

namespace App\Http\Requests\Api\V1\Teams;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends ApiFormRequest
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
            'id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'shortName' => ['required', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
            'city' => ['required', 'string', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'baseVersion' => ['required', 'integer', Rule::in([0])],
            'idempotencyKey' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->string('id')->trim()->upper()->toString(),
            'name' => $this->string('name')->squish()->toString(),
            'shortName' => $this->string('shortName')->trim()->upper()->toString(),
            'city' => $this->string('city')->squish()->toString(),
            'idempotencyKey' => str($this->header('Idempotency-Key', ''))->trim()->upper()->toString(),
        ]);
    }
}
