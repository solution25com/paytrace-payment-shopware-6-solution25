<?php

namespace PayTrace\PaymentMethods;

use PayTrace\Gateways\AchEcheck;

class PaymentMethods
{
  public const PAYMENT_METHODS = [
    CreditCardPaymentMethod::class,
      ACHPaymentMethod::class,
  ];
}