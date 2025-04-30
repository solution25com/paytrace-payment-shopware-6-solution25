<?php

namespace PayTrace\Gateways;

use PayTrace\Library\Constants\TransactionStatuses;
use PayTrace\Service\PayTraceConfigService;
use PayTrace\Service\PayTraceTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AchEcheck extends AbstractPaymentHandler
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private PayTraceTransactionService $payTraceTransactionService;
    private EntityRepository $orderTransactionRepository;


  public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        PayTraceTransactionService $payTraceTransactionService,
        EntityRepository $orderTransactionRepository
    )
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->payTraceTransactionService = $payTraceTransactionService;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }


  public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
  {
    $payTraceTransactionId = $request->getPayload()->get('payTrace_transaction_id');

    $transactionId = $transaction->getOrderTransactionId();
    $criteria = new Criteria([$transactionId]);
    $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();
    $orderId = $orderTransaction->getOrderId();

    $this->transactionStateHandler->processUnconfirmed($transaction->getOrderTransactionId(), $context);
    $status = TransactionStatuses::UNCONFIRMED->value;
    $this->payTraceTransactionService->addTransaction($orderId, "", $payTraceTransactionId, $status, $context);

    return null;
  }

  public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
  {
    // TODO: Implement supports() method.
    return false;
  }
}
