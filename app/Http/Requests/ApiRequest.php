<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use LogicException;

abstract class ApiRequest extends FormRequest
{
    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated application user is required.');
        }

        return $user;
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
