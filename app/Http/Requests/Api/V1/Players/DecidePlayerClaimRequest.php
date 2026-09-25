<?php

namespace App\Http\Requests\Api\V1\Players;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class DecidePlayerClaimRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $claimRequest = $this->route('claimRequest');

        return $claimRequest?->player_id === $this->route('player')?->id
            && ($this->user()?->can('decideClaim', $this->route('player')) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'baseVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
