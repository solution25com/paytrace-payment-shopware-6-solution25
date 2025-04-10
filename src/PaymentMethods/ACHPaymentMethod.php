<?php

namespace PayTrace\PaymentMethods;

use PayTrace\Gateways\AchEcheck;

class ACHPaymentMethod implements PaymentMethodInterface
{

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'PayTrace ACH (eCheck) Payment';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'PayTrace ACH (eCheck) Payment';
    }

    /**
     * @inheritDoc
     */
    public function getPaymentHandler(): string
    {
        return AchEcheck::class;
    }
}