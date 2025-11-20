<?php

namespace solu1Paytrace\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use solu1Paytrace\Core\Content\Transaction\PayTraceTransactionCollection;
use solu1Paytrace\Core\Content\Transaction\PayTraceTransactionEntity;

class PayTraceTransactionService
{
    /** @var EntityRepository<PayTraceTransactionCollection> */
    private EntityRepository $payTraceTransactionRepository;

    /** @var EntityRepository<OrderCollection> */
    private EntityRepository $orderRepository;

    /** @var EntityRepository<OrderTransactionCollection> */
    private EntityRepository $orderTransactionRepository;

    /**
     * @param EntityRepository<PayTraceTransactionCollection> $payTraceTransactionRepository
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        EntityRepository $payTraceTransactionRepository,
        EntityRepository $orderRepository,
        EntityRepository $orderTransactionRepository,
    ) {
        $this->payTraceTransactionRepository = $payTraceTransactionRepository;
        $this->orderRepository = $orderRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    public function addTransaction(string $orderId, string $paymentMethodName, string $transactionId, string $status, Context $context): void
    {
        $tablePayTraceId = Uuid::randomHex();
        $this->payTraceTransactionRepository->create([
            [
                'id' => $tablePayTraceId,
                'orderId' => $orderId,
                'paymentMethodName' => $paymentMethodName,
                'transactionId' => $transactionId,
                'status' => $status,
                'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ],
        ], $context);

        $this->orderRepository->upsert([[
            'id' => $orderId,
            'payTraceTransaction' => [
                'data' => [
                    'id' => $tablePayTraceId,
                    'payTraceTransactionId' => $transactionId,
                    'paymentMethodName' => $paymentMethodName,
                    'status' => $status,
                ],
            ],
        ]], $context);
    }

    public function getTransactionByOrderId(string $orderId, Context $context): ?PayTraceTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        try {
            return $this->payTraceTransactionRepository->search($criteria, $context)->last();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getOrderByTransactionId(string $transactionId, Context $context): ?OrderTransactionEntity
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
            ],
        ], $context);
    }

    public function getOrderTransactionsById(string $transactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.billingAddress');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('paymentMethod');

        return $this->orderTransactionRepository->search($criteria, $context)->first();
    }
}
