<?php

namespace PayTrace\Service;

use PayTrace\Core\Content\Transaction\PayTraceTransactionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class PayTraceTransactionService
{
  private EntityRepository $payTraceTransactionRepository;
  private EntityRepository $orderRepository;
  private EntityRepository $orderTransactionRepository;

  public function __construct(EntityRepository $payTraceTransactionRepository,
                              EntityRepository $orderRepository,
                              EntityRepository $orderTransactionRepository,
                              )
  {
    $this->payTraceTransactionRepository = $payTraceTransactionRepository;
    $this->orderRepository = $orderRepository;
    $this->orderTransactionRepository = $orderTransactionRepository;
  }

  public function addTransaction($orderId, $paymentMethodName, $transactionId, $status, $context): void
  {
    $tablePayTraceId = Uuid::randomHex();
    $this->payTraceTransactionRepository->create([
      [
        'id' => $tablePayTraceId,
        'orderId' => $orderId,
        'paymentMethodName' => $paymentMethodName,
        'transactionId' => $transactionId,
        'status' => $status,
        'createdAt' => (new \DateTime())->format('Y-m-d H:i:s')
      ]
    ], $context);

    $this->orderRepository->upsert([[
      'id' => $orderId,
      'payTraceTransaction' => [
        'data' => [
          'id' => $tablePayTraceId,
          'payTraceTransactionId' => $transactionId,
          'paymentMethodName' => $paymentMethodName,
          'status' => $status,
        ]
      ]
    ]], $context);

  }

  public function getTransactionByOrderId(string $orderId, Context $context): null|Entity
  {
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('orderId', $orderId));
    try {
      return $this->payTraceTransactionRepository->search($criteria, $context)->last();
    } catch (\Exception $e) {
      return null;
    }
  }

  public function getOrderByTransactionId(string $transactionId, Context $context): null|Entity
  {
    $criteria = new Criteria([$transactionId]);
    try {
      return $this->orderTransactionRepository->search($criteria, $context)->last();
    } catch (\Exception $e) {
      return null;
    }
  }

  public function updateTransactionStatus(string $transactionId, string $newStatus, Context $context): void
  {
    $this->payTraceTransactionRepository->update([
      [
        'id' => $transactionId,
        'status' => $newStatus,
      ]
    ], $context);
  }

}