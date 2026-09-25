<?php

namespace App\Http\Requests\Api\V1\Teams;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class DecideTeamOwnershipTransferRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transfer = $this->route('ownershipTransfer');

        return $transfer?->team_id === $this->route('team')?->id
            && $transfer?->to_user_id === $this->user()?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'baseVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
