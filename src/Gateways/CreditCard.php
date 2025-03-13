<?php

namespace PayTrace\Gateways;

use PayTrace\Library\Constants\TransactionStatuses;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditCard implements SynchronousPaymentHandlerInterface
{
    private OrderTransactionStateHandler $transactionStateHandler;

    public function __construct(
      OrderTransactionStateHandler $transactionStateHandler,
    )
    {
        $this->transactionStateHandler = $transactionStateHandler;
    }

  public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
  {
//    $authorizeOption = $this->configService->getConfig('authorizeAndCapture');
    $context = $salesChannelContext->getContext();
    $orderId = $transaction->getOrder()->getId();
    $paymentMethodName = $salesChannelContext->getPaymentMethod()->getTranslated()['name'];
    $nmiTransactionId = $dataBag->get('payTrace_transaction_id') ?? null;


      $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context);
      $status = TransactionStatuses::PAID->value;


  }

}