<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use App\Http\Requests\ApiRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->can('update', $order) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array', 'min:1', 'max:10'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100'],
            'items.*.unit_price' => ['required_with:items', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['status', 'notes', 'items'])) {
                    $validator->errors()->add('order', 'At least one order field must be provided.');
                }
            },
        ];
    }
}
