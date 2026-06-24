<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\PaymentBusinessRuleException;
use Illuminate\Contracts\Container\Container;

class PaymentGatewayManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function gateway(string $gatewayName): PaymentGatewayInterface
    {
        $gatewayClass = config("payment.gateways.{$gatewayName}");

        if (! is_string($gatewayClass)) {
            throw PaymentBusinessRuleException::unsupportedGateway($gatewayName);
        }

        $gateway = $this->container->make($gatewayClass);

        if (! $gateway instanceof PaymentGatewayInterface || $gateway->getGatewayName() !== $gatewayName) {
            throw PaymentBusinessRuleException::invalidGateway($gatewayName);
        }

        return $gateway;
    }
}
