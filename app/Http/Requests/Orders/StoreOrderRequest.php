<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\ApiRequest;

class StoreOrderRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
        ];
    }
}
