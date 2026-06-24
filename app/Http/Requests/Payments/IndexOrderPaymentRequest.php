<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiRequest;
use App\Models\Order;

class IndexOrderPaymentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->can('view', $order) === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
