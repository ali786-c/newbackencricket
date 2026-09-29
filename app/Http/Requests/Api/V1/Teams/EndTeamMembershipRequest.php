<?php

namespace App\Http\Requests\Api\V1\Teams;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EndTeamMembershipRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $membership = $this->route('membership');

        return $user !== null && (
            $user->can('update', $this->route('team'))
            || $membership?->player?->claimed_user_id === $user->id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leftAtUtc' => ['sometimes', 'date_format:Y-m-d\TH:i:s\Z'],
            'teamRole' => ['sometimes', 'nullable', Rule::in(['member', 'captain', 'vice_captain', 'wicketkeeper'])],
            'baseVersion' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['leftAtUtc', 'teamRole'])) {
                $validator->errors()->add('request', 'A membership change is required.');
            }
        }];
    }
}
