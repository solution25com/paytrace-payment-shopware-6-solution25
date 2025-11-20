<?php

namespace solu1Paytrace\PaymentMethods;

use solu1Paytrace\Gateways\AchEcheck;

class PaymentMethods
{
    public const PAYMENT_METHODS = [
    CreditCardPaymentMethod::class,
      ACHPaymentMethod::class,
    ];
}
