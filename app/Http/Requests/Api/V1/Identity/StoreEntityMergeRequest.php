<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreEntityMergeRequest extends ApiFormRequest
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
            'entityType' => ['required', Rule::in(['player', 'team'])],
            'retiredId' => ['required', 'string', 'ulid', 'different:survivingId'],
            'survivingId' => ['required', 'string', 'ulid'],
            'retiredBaseVersion' => ['required', 'integer', 'min:1'],
            'survivingBaseVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
