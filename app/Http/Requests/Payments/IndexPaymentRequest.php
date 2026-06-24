<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentStatus;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class IndexPaymentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'gateway' => ['sometimes', 'string', Rule::in(array_keys(config('payment.gateways', [])))],
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
