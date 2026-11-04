<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class GatewayManager
{
    public static function make(string $name): Gateway
    {
        return match ($name) {
            'test' => new TestGateway(),
            'chapa' => new ChapaGateway(),
            'stripe' => new StripeGateway(),
            default => throw new InvalidArgumentException("Unknown payment gateway [$name]."),
        };
    }
}
