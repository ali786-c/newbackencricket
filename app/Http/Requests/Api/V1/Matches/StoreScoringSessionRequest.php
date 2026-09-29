<?php

namespace App\Http\Requests\Api\V1\Matches;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreScoringSessionRequest extends ApiFormRequest
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
            'deviceId' => ['required', 'ulid'],
            'baseServerVersion' => ['required', 'integer', 'min:0'],
            'takeover' => ['sometimes', 'boolean'],
        ];
    }
}
