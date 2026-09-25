<?php

namespace App\Http\Requests\Api\V1\Teams;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class UpdateTeamRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('team')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'shortName' => ['sometimes', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
            'city' => ['sometimes', 'string', 'min:1', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'baseVersion' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $editable = ['name', 'shortName', 'city', 'description'];
            if (! collect($editable)->contains(fn (string $field): bool => $this->has($field))) {
                $validator->errors()->add('request', 'At least one team field must be provided.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if ($this->has('name')) {
            $values['name'] = $this->string('name')->squish()->toString();
        }
        if ($this->has('shortName')) {
            $values['shortName'] = $this->string('shortName')->trim()->upper()->toString();
        }
        if ($this->has('city')) {
            $values['city'] = $this->string('city')->squish()->toString();
        }
        $this->merge($values);
    }
}
