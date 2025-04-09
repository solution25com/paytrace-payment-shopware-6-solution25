<?php

namespace PayTrace\PaymentMethods;

use PayTrace\Gateways\CreditCard;

class CreditCardPaymentMethod implements PaymentMethodInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'PayTrace Credit Card';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'PayTrace Credit Card Payment';
    }

    /**
     * @inheritDoc
     */
    public function getPaymentHandler(): string
    {
        return CreditCard::class;
    }
}
