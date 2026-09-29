<?php

namespace App\Http\Requests\Api\V1\Matches;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StoreEventBatchRequest extends ApiFormRequest
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
            'scoringSessionId' => ['required', 'ulid'],
            'deviceId' => ['required', 'ulid'],
            'baseServerVersion' => ['required', 'integer', 'min:0'],
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.eventId' => ['required', 'ulid', 'distinct'],
            'events.*.inningsId' => ['nullable', 'ulid'],
            'events.*.sequence' => ['required', 'integer', 'min:1'],
            'events.*.eventType' => ['required', 'string', 'max:40'],
            'events.*.eventSchemaVersion' => ['required', 'integer', 'min:1'],
            'events.*.ruleProfileVersion' => ['required', 'integer', 'min:1'],
            'events.*.occurredAtUtc' => ['required', 'date'],
            'events.*.payload' => ['required', 'array'],
        ];
    }
}
