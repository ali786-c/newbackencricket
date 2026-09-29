<?php

namespace App\Http\Requests\Api\V1\Sync;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreSyncOperationRequest extends ApiFormRequest
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
            'outbox_id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'operation' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_.-]+$/'],
            'entity_type' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]+$/'],
            'entity_id' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'payload' => ['required', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'outbox_id' => $this->string('outbox_id')->trim()->upper()->toString(),
            'entity_id' => $this->string('entity_id')->trim()->upper()->toString(),
        ]);
    }
}
