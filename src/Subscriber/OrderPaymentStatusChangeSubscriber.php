<?php

namespace PayTrace\Subscriber;

use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceTransactionService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPaymentStatusChangeSubscriber implements EventSubscriberInterface
{
  private PayTraceTransactionService $payTraceTransactionService;
  private EntityRepository $orderRepository;
  private PayTraceApiService $payTraceApiService;

  public function __construct(
    PayTraceTransactionService $payTraceTransactionService,
    PayTraceApiService $payTraceApiService,
    EntityRepository $orderRepository
  ) {
    $this->payTraceTransactionService = $payTraceTransactionService;
    $this->payTraceApiService = $payTraceApiService;
    $this->orderRepository = $orderRepository;
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

    $orderId = $this->payTraceTransactionService->getOrderByTransactionId($event->getEntityId(), $context)?->getOrderId();

    if (!$orderId) {
      throw new \Exception("Order ID not found for transaction: " . $event->getEntityId());
    }

    match ($nextState) {
      'paid' => $this->handleCapture($orderId, $context),
      'refunded' => $this->handleRefund($orderId, $context),
      default => null
    };
  }

  private function handleCapture(string $orderId, $context): void
  {
    $transaction = $this->payTraceTransactionService->getTransactionByOrderId($orderId, $context);

    if (!$transaction) {
      throw new StateMachineException(400, 400, "Transaction not found for order: $orderId");
    }

    $transactionId = $transaction->getTransactionId();
    $orderAmount = $this->getOrderTotalAmount($orderId, $context);

    $postData = [
      "transactionId" => $transactionId,
      "amount" => $orderAmount
    ];

    $response = $this->payTraceApiService->processCapture($postData);

    if (!isset($response['status']) || $response['status'] !== 'success') {
      throw new StateMachineException(400, 400, json_encode($response));
    }
  }

  private function handleRefund(string $orderId, $context): void
  {
    try {
      $transaction = $this->payTraceTransactionService->getTransactionByOrderId($orderId, $context);

      if (!$transaction) {
        throw new StateMachineException(400, 400, "Invalid transaction status for refund.");
      }

      $transactionId = $transaction->getTransactionId();
      $orderAmount = $this->getOrderTotalAmount($orderId, $context);

      $postData = [
        "transactionId" => $transactionId,
        "amount" => $orderAmount
      ];

      $response = $this->payTraceApiService->captureRefund($postData);

      if ($response[0]['status'] !== 'success') {
        throw new StateMachineException(400, 400, json_encode($response));
      }
    } catch (\Exception $exception) {
      error_log('Error in onStateMachineTransition: ' . $exception->getMessage());
      throw new StateMachineException(400, 400, json_encode('Refund failed.'));
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
