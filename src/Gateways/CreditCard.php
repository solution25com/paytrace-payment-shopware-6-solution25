<?php

namespace PayTrace\Gateways;

use PayTrace\Library\Constants\TransactionStatuses;
use PayTrace\Service\PayTraceConfigService;
use PayTrace\Service\PayTraceTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditCard implements SynchronousPaymentHandlerInterface
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private PayTraceTransactionService $payTraceTransactionService;
    private PayTraceConfigService $payTraceConfigService;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        PayTraceTransactionService $payTraceTransactionService,
        PayTraceConfigService $payTraceConfigService
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->payTraceTransactionService = $payTraceTransactionService;
        $this->payTraceConfigService = $payTraceConfigService;
    }

    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {
        $authorizeOption = $this->payTraceConfigService->getConfig('authorizeAndCapture');
        $context = $salesChannelContext->getContext();
        $orderId = $transaction->getOrder()->getId();
        $paymentMethodName = $salesChannelContext->getPaymentMethod()->getTranslated()['name'];
        $payTraceTransactionId = $dataBag->get('payTrace_transaction_id') ?? null;

        $transactionState = $authorizeOption
            ? TransactionStatuses::AUTHORIZED->value
            : TransactionStatuses::PAID->value;
        $transactionMethod = $authorizeOption ? 'authorize' : 'paid';

        $this->transactionStateHandler->{$transactionMethod}($transaction->getOrderTransaction()->getId(), $context);

        $this->payTraceTransactionService->addTransaction(
            $orderId,
            $paymentMethodName,
            $payTraceTransactionId,
            $transactionState,
            $context
        );
    }
}
