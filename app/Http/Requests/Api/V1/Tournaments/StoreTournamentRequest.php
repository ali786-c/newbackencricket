<?php

namespace App\Http\Requests\Api\V1\Tournaments;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreTournamentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'city' => ['required', 'string', 'max:100'],
            'season' => ['required', 'string', 'max:40'],
            'startsAtUtc' => ['required', 'date'],
            'endsAtUtc' => ['required', 'date', 'after:startsAtUtc'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logoPath' => ['nullable', 'string', 'max:500'],
            'ruleProfile.version' => ['required', 'integer', Rule::in([1])],
            'ruleProfile.oversPerInnings' => ['required', 'integer', 'between:1,100'],
            'ruleProfile.ballsPerOver' => ['required', 'integer', 'between:4,8'],
            'ruleProfile.playersPerSide' => ['required', 'integer', 'between:2,15'],
            'ruleProfile.wicketsPerInnings' => ['required', 'integer', 'min:1', 'lt:ruleProfile.playersPerSide'],
            'ruleProfile.ballType' => ['required', Rule::in(['tennis', 'tape', 'leather'])],
            'pointsRules.win' => ['required', 'integer', 'between:0,10'],
            'pointsRules.tie' => ['required', 'integer', 'between:0,10'],
            'pointsRules.noResult' => ['required', 'integer', 'between:0,10'],
            'baseVersion' => ['required', 'integer', Rule::in([0])],
            'idempotencyKey' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->string('id')->trim()->upper()->toString(),
            'name' => $this->string('name')->squish()->toString(),
            'city' => $this->string('city')->squish()->toString(),
            'season' => $this->string('season')->squish()->toString(),
            'idempotencyKey' => str($this->header('Idempotency-Key', ''))->trim()->upper()->toString(),
        ]);
    }
}
