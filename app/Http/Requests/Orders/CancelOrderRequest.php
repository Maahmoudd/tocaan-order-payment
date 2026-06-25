<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\ApiRequest;
use App\Models\Order;

class CancelOrderRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->can('update', $order) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
