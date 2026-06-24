<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->can('view', $order) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $gatewayNames = array_keys(config('payment.gateways', []));

        return [
            'gateway' => ['required', 'string', Rule::in($gatewayNames)],
            'payload' => ['required', 'array'],
            'payload.card_token' => ['required_if:gateway,credit_card', 'string', 'max:255'],
            'payload.paypal_order_id' => ['required_if:gateway,paypal', 'string', 'max:255'],
            'payload.payment_method_id' => ['required_if:gateway,stripe', 'string', 'max:255'],
            'payload.simulate_failure' => ['sometimes', 'boolean'],
        ];
    }
}
