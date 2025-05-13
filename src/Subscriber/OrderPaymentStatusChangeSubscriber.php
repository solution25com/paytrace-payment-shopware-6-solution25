<?php

namespace PayTrace\Subscriber;

use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceTransactionService;
use PayTrace\Core\Content\Transaction\PayTraceTransactionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

class OrderPaymentStatusChangeSubscriber implements EventSubscriberInterface
{
  private PayTraceTransactionService $payTraceTransactionService;
  private PayTraceApiService $payTraceApiService;

    /** @var EntityRepository<OrderCollection> */
  private EntityRepository $orderRepository;

    /** @var EntityRepository<PaymentMethodCollection> */
  private EntityRepository $paymentMethodRepository;
  private LoggerInterface $logger;


    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
  public function __construct(
    PayTraceTransactionService $payTraceTransactionService,
    PayTraceApiService $payTraceApiService,
    EntityRepository $orderRepository,
    EntityRepository $paymentMethodRepository,
    LoggerInterface $logger
  ) {
    $this->payTraceTransactionService = $payTraceTransactionService;
    $this->payTraceApiService = $payTraceApiService;
    $this->orderRepository = $orderRepository;
    $this->paymentMethodRepository = $paymentMethodRepository;
    $this->logger = $logger;
  }

  public static function getSubscribedEvents(): array
  {
    return [
      StateMachineTransitionEvent::class => 'onStateMachineTransition',
    ];
  }

  public function onStateMachineTransition(StateMachineTransitionEvent $event): void
  {
    $nextState = strtolower($event->getToPlace()->getTechnicalName());
    $context = $event->getContext();

    $orderTransaction = $this->payTraceTransactionService->getOrderByTransactionId($event->getEntityId(), $context);
    $paymentMethod = $this->paymentMethodRepository->search(new Criteria([$orderTransaction->getPaymentMethodId()]), $context)->first();
    $payTraceHandlerIdentifier = $paymentMethod->getHandlerIdentifier();

    if ($payTraceHandlerIdentifier !== 'PayTrace\Gateways\CreditCard') {
      return;
    }

    if (empty($orderTransaction)) {
      return;
    }

    $orderId = $orderTransaction->getOrderId();

    if (!$orderId) {
      throw new \Exception("Order ID not found for transaction: " . $event->getEntityId());
    }

    $payTraceTransaction = $this->payTraceTransactionService->getTransactionByOrderId($orderId, $context);
    $payTraceTransactionId = $payTraceTransaction->getTransactionId();

    if (!$payTraceTransactionId) {
      throw new \Exception("PayTrace transaction not found for order ID: " . $orderId);
    }

    $currentStatus = strtolower($payTraceTransaction->getStatus() ?? '');

    if ($nextState === 'paid') {
      if ($currentStatus !== 'paid') {
        $this->handleCapture($orderId, $context, $payTraceTransactionId, $payTraceTransaction->getId());
      }
    } elseif ($nextState === 'cancelled') {
      $this->handleVoid($orderId, $context, $payTraceTransactionId, $payTraceTransaction->getId());
    } elseif ($nextState === 'refunded') {
      $this->handleRefund($orderId, $context, $payTraceTransactionId, $payTraceTransaction->getId());
    }
  }

  private function handleCapture(string $orderId, Context $context, string $payTraceTransactionId, string $transactionId): void
  {
    $orderAmount = $this->getOrderTotalAmount($orderId, $context);

    $postData = [
      "transactionId" => $payTraceTransactionId,
      "amount" => $orderAmount
    ];

    $response = $this->payTraceApiService->processCapture($postData);

    if (!isset($response['status']) || $response['status'] !== 'success') {
      $this->logger->error('PayTrace capture failed', ['response' => $response]);
      throw new StateMachineException(400,'PAYTRACE_CAPTURE_FAILED',(string) json_encode($response));
    }

    $this->payTraceTransactionService->updateTransactionStatus($transactionId, 'paid', $context);
  }

  private function handleRefund(string $orderId,Context $context, string $payTraceTransactionId, string $transactionId): void
  {
    try {
      $orderAmount = $this->getOrderTotalAmount($orderId, $context);

      $postData = [
        "transactionId" => $payTraceTransactionId,
        "amount" => $orderAmount
      ];

      $response = $this->payTraceApiService->captureRefund($postData);

      if ($response[0]['status'] !== 'success') {
        $this->logger->error('PayTrace refund failed', ['response' => $response]);
        throw new StateMachineException(400, 'PAYMENT_PROCESSING_FAILED', (string) json_encode($response));
      }

      $this->payTraceTransactionService->updateTransactionStatus($transactionId, 'refunded', $context);
    } catch (\Exception $exception) {
      $this->logger->error('Refund failed', ['exception' => $exception]);
      throw new StateMachineException(400, 'REFUND_FAILED', 'Refund failed.');
    }
  }

  private function handleVoid(string $orderId, Context $context, string $payTraceTransactionId, string $transactionId): void
  {
    try {

      $postData = [
        "transactionId" => $payTraceTransactionId,
      ];

      $response = $this->payTraceApiService->voidTransaction($postData);

      if ($response[0]['status'] !== 'success') {
        $this->logger->error('PayTrace void failed', ['response' => $response]);
        throw new StateMachineException(
          400,
          'PAYMENT_CAPTURE_FAILED',
            (string) json_encode($response),
          ['response' => $response]
        );
      }

      $this->payTraceTransactionService->updateTransactionStatus($transactionId, 'cancelled', $context);
    } catch (\Exception $exception) {
      $this->logger->error('Void (cancel) failed', ['exception' => $exception]);
      throw new StateMachineException(400, 'PAYTRACE_VOID_FAILED', 'Void (cancel) failed.');
    }
  }

  private function getOrderTotalAmount(string $orderId,Context $context): float
  {
    $criteria = new Criteria([$orderId]);
    $criteria->addAssociation('currency');

    $order = $this->orderRepository->search($criteria, $context)->first();

    if (!$order) {
      throw new \Exception("Order not found.");
    }

    return $order->getAmountTotal();
  }
}

