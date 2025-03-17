<?php

namespace PayTrace\Subscriber;

use PayTrace\Library\Constants\TransactionStatuses;
use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceTransactionService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefundOrderEventSubscriber implements EventSubscriberInterface
{
  private PayTraceTransactionService $payTraceTransactionService;
  private EntityRepository $orderTransactionRepository;
  private EntityRepository $orderRepository;
  private PayTraceApiService $payTraceApiService;

  public function __construct(
    PayTraceTransactionService $payTraceTransactionService,
    EntityRepository $orderTransactionRepository,
    PayTraceApiService $payTraceApiService,
    EntityRepository $orderRepository,
  ) {
    $this->payTraceTransactionService = $payTraceTransactionService;
    $this->orderTransactionRepository = $orderTransactionRepository;
    $this->payTraceApiService = $payTraceApiService;
    $this->orderRepository = $orderRepository;

  }

  public static function getSubscribedEvents()
  {
    return [
      StateMachineTransitionEvent::class => 'onStateMachineTransition',
    ];
  }

  public function onStateMachineTransition(StateMachineTransitionEvent $event): void
  {
    $nextState = strtolower($event->getToPlace()->getTechnicalName());

    if ($nextState === 'refunded') {
      try {
        $orderId = $this->payTraceTransactionService
          ->getOrderByTransactionId($event->getEntityId(), $event->getContext())
          ->getOrderId();

        $transaction = $this->payTraceTransactionService
          ->getTransactionByOrderId($orderId, $event->getContext());

        $transactionId = $transaction->getTransactionId();

        $orderAmount = $this->getOrderTotalAmount($orderId, $event->getContext());

        if ($transaction && $transaction->getStatus() == TransactionStatuses::PAID->value) {
          $postData = [
            "merchant_id" => '85774',
            "batch_items" => [
              [
                "transaction_id" => $transactionId,
                "custom_dba" => "Refund...",
                "amount" => $orderAmount
              ]
            ]
          ];

          // The transaction should be settled to be refunded.
          $response = $this->payTraceApiService->captureRefund($postData);

          if (isset($response[0]['status']) && $response[0]['status'] == 'success') {
            return;

          } else {
            // todo :need to stop or reverse transition (Keep PAID or reverse to PAID)
            throw new StateMachineException(400, 400, json_encode($response));
          }
        }
      } catch (\Exception $exception) {
        error_log('Error in onStateMachineTransition: ' . $exception->getMessage());
        throw new StateMachineException(400, 400, json_encode('Refund failed.'));
      }
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
