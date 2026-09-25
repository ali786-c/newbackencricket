<?php

namespace App\Http\Requests\Api\V1\Teams;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreTeamOwnershipTransferRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('requestOwnershipTransfer', $this->route('team')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'toUserId' => ['required', 'string', 'ulid', 'exists:users,id'],
            'baseVersion' => ['required', 'integer', 'min:1'],
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
