<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): never
    {
        $requestId = $this->header('X-Request-ID');

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'The request contains invalid data.',
                'requestId' => is_string($requestId) && Str::isUlid($requestId)
                    ? Str::upper($requestId)
                    : (string) Str::ulid(),
                'fieldErrors' => $validator->errors()->toArray(),
                'conflict' => null,
            ],
        ], 422));
    }
}
