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

class AchEcheck implements SynchronousPaymentHandlerInterface
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private PayTraceTransactionService $payTraceTransactionService;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        PayTraceTransactionService $payTraceTransactionService,
    )
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->payTraceTransactionService = $payTraceTransactionService;
    }

    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();
        $orderId = $transaction->getOrder()->getId();
        $paymentMethodName = $salesChannelContext->getPaymentMethod()->getTranslated()['name'];
        $payTraceTransactionId = $dataBag->get('payTrace_transaction_id') ?? null;

        $this->transactionStateHandler->processUnconfirmed($transaction->getOrderTransaction()->getId(), $context);
        $status = TransactionStatuses::UNCONFIRMED->value;
        $this->payTraceTransactionService->addTransaction($orderId, $paymentMethodName, $payTraceTransactionId, $status, $context);
    }
}
