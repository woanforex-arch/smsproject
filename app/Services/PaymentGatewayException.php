<?php

namespace App\Services;

use RuntimeException;

class PaymentGatewayException extends RuntimeException
{
    public static function misconfigured(): self
    {
        return new self('Payment processing is not configured. Please contact the organiser.');
    }
}