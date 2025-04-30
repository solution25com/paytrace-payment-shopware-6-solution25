<?php

namespace PayTrace\Subscriber;

use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceTransactionService;
use PayTrace\Core\Content\Transaction\PayTraceTransactionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPaymentStatusChangeSubscriber implements EventSubscriberInterface
{
  private PayTraceTransactionService $payTraceTransactionService;
  private PayTraceApiService $payTraceApiService;
  private EntityRepository $orderRepository;
  private LoggerInterface $logger;

  public function __construct(
    PayTraceTransactionService $payTraceTransactionService,
    PayTraceApiService $payTraceApiService,
    EntityRepository $orderRepository,
    LoggerInterface $logger
  ) {
    $this->payTraceTransactionService = $payTraceTransactionService;
    $this->payTraceApiService = $payTraceApiService;
    $this->orderRepository = $orderRepository;
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

    if (!$orderTransaction) {
      throw new \Exception("Order transaction not found for transaction ID: " . $event->getEntityId());
    }

    $orderId = $orderTransaction->getOrderId();

    if (!$orderId) {
      throw new \Exception("Order ID not found for transaction: " . $event->getEntityId());
    }

    $payTraceTransaction = $this->payTraceTransactionService->getTransactionByOrderId($orderId, $context);

    if (!$payTraceTransaction) {
      throw new \Exception("PayTrace transaction not found for order ID: " . $orderId);
    }

    $currentStatus = strtolower($payTraceTransaction->getStatus() ?? '');

    if ($nextState === 'paid') {
      if ($currentStatus !== 'paid') {
        $this->handleCapture($orderId, $context, $payTraceTransaction);
      }
    } elseif ($nextState === 'cancelled') {
      $this->handleVoid($orderId, $context, $payTraceTransaction);
    } elseif ($nextState === 'refunded') {
      $this->handleRefund($orderId, $context, $payTraceTransaction);
    }
  }

  private function handleCapture(string $orderId, $context, PayTraceTransactionEntity $transaction): void
  {
    $transactionId = $transaction->getTransactionId();
    $orderAmount = $this->getOrderTotalAmount($orderId, $context);

    $postData = [
      "transactionId" => $transactionId,
      "amount" => $orderAmount
    ];

    $response = $this->payTraceApiService->processCapture($postData);

    if (!isset($response['status']) || $response['status'] !== 'success') {
      $this->logger->error('PayTrace capture failed', ['response' => $response]);
      throw new StateMachineException(400, 400, json_encode($response));
    }

    $this->payTraceTransactionService->updateTransactionStatus($transaction->getId(), 'paid', $context);
  }

  private function handleRefund(string $orderId, $context, PayTraceTransactionEntity $transaction): void
  {
    try {
      $transactionId = $transaction->getTransactionId();
      $orderAmount = $this->getOrderTotalAmount($orderId, $context);

      $postData = [
        "transactionId" => $transactionId,
        "amount" => $orderAmount
      ];

      $response = $this->payTraceApiService->captureRefund($postData);

      if ($response[0]['status'] !== 'success') {
        $this->logger->error('PayTrace refund failed', ['response' => $response]);
        throw new StateMachineException(400, 400, json_encode($response));
      }

      $this->payTraceTransactionService->updateTransactionStatus($transaction->getId(), 'refunded', $context);
    } catch (\Exception $exception) {
      $this->logger->error('Refund failed', ['exception' => $exception]);
      throw new StateMachineException(400, 400, 'Refund failed.');
    }
  }

  private function handleVoid(string $orderId, $context, PayTraceTransactionEntity $transaction): void
  {
    try {
      $transactionId = $transaction->getTransactionId();

      $postData = [
        "transactionId" => $transactionId,
      ];

      $response = $this->payTraceApiService->voidTransaction($postData);

      if ($response[0]['status'] !== 'success') {
        $this->logger->error('PayTrace void failed', ['response' => $response]);
        throw new StateMachineException(400, 400, json_encode($response));
      }

      $this->payTraceTransactionService->updateTransactionStatus($transaction->getId(), 'cancelled', $context);
    } catch (\Exception $exception) {
      $this->logger->error('Void (cancel) failed', ['exception' => $exception]);
      throw new StateMachineException(400, 400, 'Void (cancel) failed.');
    }
  }

  private function getOrderTotalAmount(string $orderId, $context): float
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
