<?php

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\PaymentBusinessRuleException;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\PaymentGatewayManager;

it('resolves every configured payment gateway', function () {
    $manager = app(PaymentGatewayManager::class);

    foreach (array_keys(config('payment.gateways')) as $gatewayName) {
        $gateway = $manager->gateway($gatewayName);

        expect($gateway)->toBeInstanceOf(PaymentGatewayInterface::class)
            ->and($gateway->getGatewayName())->toBe($gatewayName);
    }
});

it('rejects an unsupported payment gateway', function () {
    app(PaymentGatewayManager::class)->gateway('missing');
})->throws(PaymentBusinessRuleException::class, 'not supported');

it('rejects a configured gateway whose name does not match', function () {
    config()->set('payment.gateways.alias', CreditCardGateway::class);

    app(PaymentGatewayManager::class)->gateway('alias');
})->throws(PaymentBusinessRuleException::class, 'is invalid');
