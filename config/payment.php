<?php

use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;
use App\Payments\Gateways\StripeGateway;

return [
    'gateways' => [
        'credit_card' => CreditCardGateway::class,
        'paypal' => PayPalGateway::class,
        'stripe' => StripeGateway::class,
    ],
];
