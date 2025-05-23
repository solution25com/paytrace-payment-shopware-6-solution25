<?php

namespace PayTrace\Gateways;

use PayTrace\Library\Constants\TransactionStatuses;
use PayTrace\Service\PayTraceConfigService;
use PayTrace\Service\PayTraceTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\AbstractGateway;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;

class CreditCard extends AbstractPaymentHandler
{
  private OrderTransactionStateHandler $transactionStateHandler;
  private PayTraceTransactionService $payTraceTransactionService;
  private PayTraceConfigService $payTraceConfigService;

  /** @var EntityRepository<OrderTransactionCollection> */
  private EntityRepository $orderTransactionRepository;

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
  public function __construct(
    OrderTransactionStateHandler $transactionStateHandler,
    PayTraceTransactionService $payTraceTransactionService,
    PayTraceConfigService $payTraceConfigService,
    EntityRepository $orderTransactionRepository,

  )
  {
    $this->transactionStateHandler = $transactionStateHandler;
    $this->payTraceTransactionService = $payTraceTransactionService;
    $this->payTraceConfigService = $payTraceConfigService;
    $this->orderTransactionRepository = $orderTransactionRepository;
  }

  public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
  {
    return true;
  }

  public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
  {
    $payTraceTransactionId = $request->getPayload()->get('payTrace_transaction_id');

    $authorizeOption = $this->payTraceConfigService->getConfig('authorizeAndCapture');
    $transactionState = $authorizeOption == 'auth' ? TransactionStatuses::AUTHORIZED->value : TransactionStatuses::PAID->value;
    $transactionMethod = $authorizeOption == 'auth' ? 'authorize' : 'paid';

    $transactionId = $transaction->getOrderTransactionId();
    $criteria = new Criteria([$transactionId]);

    /** @var OrderTransactionEntity|null $orderTransaction */
    $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

    if (!$orderTransaction instanceof OrderTransactionEntity) {
      throw new \RuntimeException('OrderTransaction not found for ID ' . $transactionId);
    }

    $orderId = $orderTransaction->getOrderId();

    $this->payTraceTransactionService->addTransaction($orderId, "",(string) $payTraceTransactionId, $transactionState, $context);
    $this->transactionStateHandler->{$transactionMethod}($transactionId, $context);

    return null;
  }
}
