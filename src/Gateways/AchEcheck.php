<?php

namespace solu1Paytrace\Gateways;

use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use solu1Paytrace\Library\Constants\TransactionStatuses;
use solu1Paytrace\Service\PayTraceApiService;
use solu1Paytrace\Service\PayTraceConfigService;
use solu1Paytrace\Service\PayTraceTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AchEcheck extends AbstractPaymentHandler
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private PayTraceTransactionService $payTraceTransactionService;
    private PayTraceApiService $payTraceApiService;
    private PayTraceConfigService $payTraceConfigService;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        PayTraceTransactionService $payTraceTransactionService,
        PayTraceApiService $payTraceApiService,
        PayTraceConfigService $payTraceConfigService,
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->payTraceTransactionService = $payTraceTransactionService;
        $this->payTraceApiService = $payTraceApiService;
        $this->payTraceConfigService = $payTraceConfigService;
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        /** @var string|null $salesChannelId */
        $salesChannelId = $request->attributes->get('sw-sales-channel-id');

        /** @var string|null $flow */
        $flow = $this->payTraceConfigService->getConfig('flow', $salesChannelId);

        /** @var OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->payTraceTransactionService->getOrderTransactionsById(
            $transaction->getOrderTransactionId(),
            $context
        );

        if ($flow === 'payment_order') {
            $this->paymentFirstFlow($request, $transaction, $orderTransaction, $context);
        } else {
            $this->orderFirstFlow($request, $transaction, $orderTransaction, $context);
        }

        return null;
    }

    private function paymentFirstFlow(Request $request, PaymentTransactionStruct $transaction, OrderTransactionEntity $orderTransaction, Context $context): void
    {
        $payTraceTransactionId = $request->request->get('payTrace_transaction_id') ?? '0';

        $order = $orderTransaction->getOrder();
        if ($order === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Order association not loaded on transaction.');
        }
        /** @var OrderEntity $order */

        $paymentMethod = $orderTransaction->getPaymentMethod();
        if ($paymentMethod === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Payment method not available on transaction.');
        }

        $orderId = $order->getId();
        $paymentMethodName = (string) $paymentMethod->getName();

        $this->payTraceTransactionService->addTransaction(
            $orderId,
            $paymentMethodName,
            $payTraceTransactionId,
            TransactionStatuses::PAID->value,
            $context
        );

        $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
    }

    private function orderFirstFlow(Request $request, PaymentTransactionStruct $transaction, OrderTransactionEntity $orderTransaction, Context $context): void
    {
        $order = $orderTransaction->getOrder();
        if ($order === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Order association not loaded on transaction.');
        }
        /** @var OrderEntity $order */

        $paymentDataRaw = $request->request->get('paytracePaymentData');
        if (!is_string($paymentDataRaw) || $paymentDataRaw === '') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Missing paymentData for order-first ACH flow');
        }

        /** @var array<string,mixed>|null $paymentData */
        $paymentData = json_decode($paymentDataRaw, true);
        if (!is_array($paymentData)) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Invalid JSON in paymentData for order-first ACH flow');
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Billing address is missing on order.');
        }

        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Order customer is missing on order.');
        }

        /** @var array<string, string|null> $billingData */
        $billingData = [
            'fullName' => trim((string) $billingAddress->getFirstName() . ' ' . (string) $billingAddress->getLastName()),
            'street'   => $billingAddress->getStreet(),
            'street2'  => $billingAddress->getAdditionalAddressLine1(),
            'city'     => $billingAddress->getCity(),
            'state'    => $billingAddress->getCountryState()?->getShortCode(),
            'country'  => $billingAddress->getCountry()?->getIso(),
            'zip'      => $billingAddress->getZipCode(),
            'email'    => $orderCustomer->getEmail(),
        ];

        $response = $this->payTraceApiService->processOrderFirstAchPayment(
            $paymentData,
            $billingData,
            $order->getSalesChannelId()
        );

        /** @var array<string,mixed> $response */
        $success = (bool)($response['success'] ?? false);

        if (!$success) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            $message = is_string($response['message'] ?? null) ? $response['message'] : 'Payment failed in order-first flow';
            throw new RuntimeException($message);
        }

        /** @var bool|float|int|string $transactionId */
        $transactionId = $request->getPayload()->get('payTrace_transaction_id') ?? $request->request->get('payTrace_transaction_id') ?? '0';

        $paymentMethod = $orderTransaction->getPaymentMethod();
        if ($paymentMethod === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new RuntimeException('Payment method not available on transaction.');
        }

        $this->payTraceTransactionService->addTransaction(
            $order->getId(),
            (string) $paymentMethod->getName(),
            $transactionId,
            TransactionStatuses::PAID->value,
            $context
        );

        $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        // TODO: Implement supports() method.
        return false;
    }
}
