<?php

namespace App\Http\Requests\Api\V1\Matches;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreMatchRequest extends ApiFormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'ulid', 'unique:matches,id'],
            'matchType' => ['required', 'in:simple,tournament'],
            'homeTeamId' => ['required', 'ulid', 'different:awayTeamId', 'exists:teams,id'],
            'awayTeamId' => ['required', 'ulid', 'exists:teams,id'],
            'scheduledAtUtc' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:160'],
            'rules' => ['required', 'array'],
            'rules.version' => ['required', 'integer', 'min:1'],
            'rules.oversPerInnings' => ['required', 'integer', 'min:1', 'max:100'],
            'rules.ballsPerOver' => ['required', 'integer', 'min:1', 'max:12'],
            'rules.playersPerSide' => ['required', 'integer', 'min:2', 'max:22'],
            'rules.wicketsPerInnings' => ['required', 'integer', 'min:1', 'max:21'],
            'rules.ballType' => ['required', 'in:tennis,tape,leather'],
        ];
    }
}
