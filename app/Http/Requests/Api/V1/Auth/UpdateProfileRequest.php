<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiFormRequest;

class UpdateProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:120'],
            'playingRole' => ['required', 'string', 'max:40'],
            'battingStyle' => ['nullable', 'string', 'max:40'],
            'bowlingStyle' => ['nullable', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'photoUrl' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
