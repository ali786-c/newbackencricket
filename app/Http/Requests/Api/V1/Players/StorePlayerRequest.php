<?php

namespace App\Http\Requests\Api\V1\Players;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StorePlayerRequest extends ApiFormRequest
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
            'city' => ['nullable', 'string', 'max:100'],
            'playingRole' => ['required', Rule::in(['batter', 'bowler', 'all_rounder', 'wicketkeeper'])],
            'battingStyle' => ['required', Rule::in(['right_hand', 'left_hand', 'unknown'])],
            'bowlingStyle' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'baseVersion' => ['required', 'integer', Rule::in([0])],
            'idempotencyKey' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->string('id')->trim()->upper()->toString(),
            'name' => $this->string('name')->squish()->toString(),
            'idempotencyKey' => str($this->header('Idempotency-Key', ''))->trim()->upper()->toString(),
        ]);
    }
}
