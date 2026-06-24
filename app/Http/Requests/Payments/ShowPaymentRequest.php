<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiRequest;
use App\Models\Payment;

class ShowPaymentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment instanceof Payment && $this->user()?->can('view', $payment) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
