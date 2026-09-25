<?php

namespace App\Http\Requests\Api\V1\Teams;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreTeamMembershipRequest extends ApiFormRequest
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
            'id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'playerId' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/', 'exists:players,id'],
            'joinedAtUtc' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
            'teamRole' => ['nullable', Rule::in(['member', 'captain', 'vice_captain', 'wicketkeeper'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->string('id')->trim()->upper()->toString(),
            'playerId' => $this->string('playerId')->trim()->upper()->toString(),
        ]);
    }
}
