<?php

namespace App\Http\Requests\Api\V1\Players;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlayerRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('player')) ?? false;
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
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'playingRole' => ['sometimes', Rule::in(['batter', 'bowler', 'all_rounder', 'wicketkeeper'])],
            'battingStyle' => ['sometimes', Rule::in(['right_hand', 'left_hand', 'unknown'])],
            'bowlingStyle' => ['sometimes', 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'baseVersion' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $editable = ['name', 'city', 'playingRole', 'battingStyle', 'bowlingStyle', 'bio'];
            if (! collect($editable)->contains(fn (string $field): bool => $this->has($field))) {
                $validator->errors()->add('request', 'At least one player field must be provided.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => $this->string('name')->squish()->toString()]);
        }
    }
}
