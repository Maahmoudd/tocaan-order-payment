<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends ApiRequest
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
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
