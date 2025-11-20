<?php

declare(strict_types=1);

namespace solu1Paytrace\Gateways;

use Psr\Http\Message\ResponseInterface;
use solu1Paytrace\Library\Constants\TransactionStatuses;
use solu1Paytrace\Service\PayTraceApiService;
use solu1Paytrace\Service\PayTraceConfigService;
use solu1Paytrace\Service\PayTraceTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class CreditCard extends AbstractPaymentHandler
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private PayTraceTransactionService $payTraceTransactionService;
    private PayTraceConfigService $payTraceConfigService;
    private PayTraceApiService $payTraceApiService;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        PayTraceTransactionService $payTraceTransactionService,
        PayTraceConfigService $payTraceConfigService,
        PayTraceApiService $payTraceApiService,
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->payTraceTransactionService = $payTraceTransactionService;
        $this->payTraceConfigService = $payTraceConfigService;
        $this->payTraceApiService = $payTraceApiService;
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return true;
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        /** @var string|null $salesChannelId */
        $salesChannelId = $request->attributes->get('sw-sales-channel-id');
        /** @var string|null $flow */
        $flow = $this->payTraceConfigService->getConfig('flow', $salesChannelId);

        /** @var string|null $authorizeOption */
        $authorizeOption   = $this->payTraceConfigService->getConfig('authorizeAndCapture');
        $transactionState  = $authorizeOption === 'auth' ? TransactionStatuses::AUTHORIZED->value : TransactionStatuses::PAID->value;
        $transactionMethod = $authorizeOption === 'auth' ? 'authorize' : 'paid';

        $orderTransaction = $this->payTraceTransactionService->getOrderTransactionsById($transaction->getOrderTransactionId(), $context);
        if (!$orderTransaction instanceof OrderTransactionEntity) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Order transaction not found');
        }

        if ($flow === 'payment_order') {
            $this->paymentFirstFlow($request, $transaction, $orderTransaction, $transactionMethod, $transactionState, $context);
        } else {
            $this->orderFirstFlow($request, $transaction, $orderTransaction, $transactionMethod, $transactionState, $context);
        }

        return null;
    }

    private function paymentFirstFlow(
        Request $request,
        PaymentTransactionStruct $transaction,
        OrderTransactionEntity $orderTransaction,
        string $handlerMethodName,
        string $transactionStatus,
        Context $context
    ): void {
        /** @var string|null $paytraceTransactionId */
        $paytraceTransactionId = $request->request->get('payTrace_transaction_id');

        $order = $orderTransaction->getOrder();
        if (!$order instanceof OrderEntity) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Order entity missing on transaction');
        }

        $paymentMethod = $orderTransaction->getPaymentMethod();
        $paymentMethodName = $paymentMethod ? (string) $paymentMethod->getName() : '';
        if ($paymentMethodName == '') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Payment method missing on transaction');
        }

        $orderId = (string) $order->getId();

        $this->payTraceTransactionService->addTransaction(
            $orderId,
            $paymentMethodName,
            (string) $paytraceTransactionId,
            $transactionStatus,
            $context
        );

        $this->transactionStateHandler->{$handlerMethodName}($transaction->getOrderTransactionId(), $context);
    }

    private function orderFirstFlow(
        Request $request,
        PaymentTransactionStruct $transaction,
        OrderTransactionEntity $orderTransaction,
        string $handlerMethodName,
        string $transactionStatus,
        Context $context
    ): void {
        $order = $orderTransaction->getOrder();
        if (!$order instanceof OrderEntity) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Order entity missing on transaction');
        }

        /** @var mixed $salesChannelContext */
        $salesChannelContext = $request->attributes->get('sw-sales-channel-context');

        $paymentDataRaw = $request->request->get('paytracePaymentData');
        if (!is_string($paymentDataRaw) || $paymentDataRaw === '') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Missing paymentData for order-first flow');
        }

        /** @var array<string,mixed> $paymentData */
        $paymentData = (array) json_decode($paymentDataRaw, true, 512, JSON_THROW_ON_ERROR);

        /** @var string|null $selectedCardVaultedId */
        $selectedCardVaultedId = isset($paymentData['selectedCardVaultedId']) && is_string($paymentData['selectedCardVaultedId'])
            ? $paymentData['selectedCardVaultedId']
            : null;

        /** @var array<string,mixed>|null $token */
        $token = isset($paymentData['token']) && is_array($paymentData['token']) ? $paymentData['token'] : null;

        /** @var bool $saveCard */
        $saveCard = isset($paymentData['saveCard']) ? (bool) $paymentData['saveCard'] : false;

        $billingAddress = $order->getBillingAddress();
        if (!$billingAddress instanceof OrderAddressEntity) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Billing address missing on order');
        }

        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Order customer missing on order');
        }

        $billingData = [
            'fullName' => trim((string) $billingAddress->getFirstName() . ' ' . (string) $billingAddress->getLastName()),
            'street'   => (string) $billingAddress->getStreet(),
            'street2'  => (string) $billingAddress->getAdditionalAddressLine1(),
            'city'     => (string) $billingAddress->getCity(),
            'state'    => $billingAddress->getCountryState()?->getShortCode(),
            'country'  => $billingAddress->getCountry()?->getIso(),
            'zip'      => (string) $billingAddress->getZipCode(),
            'email'    => (string) $orderCustomer->getEmail(),
        ];

        $salesChannelId  = (string) $order->getSalesChannelId();
        /** @var string|null $cfg */
        $cfg             = $this->payTraceConfigService->getConfig('authorizeAndCapture', $salesChannelId);
        $authAndCapture  = $cfg ?? 'capture';


        $amount = number_format($orderTransaction->getAmount()->getTotalPrice(), 2, '.', '');

        /** @var array<string,mixed>|ResponseInterface|null $apiResponse */
        $apiResponse = null;

        if ($selectedCardVaultedId) {
            $apiResponse = $this->payTraceApiService->processVaultedPayment(
                [
                    'selectedCardVaultedId' => $selectedCardVaultedId,
                    'amount'                => $amount,
                ],
                $salesChannelContext
            );
        } elseif (
            $token !== null
            && !empty($token['hpf_token'])
            && !empty($token['enc_key'])
        ) {
            if ($authAndCapture === 'auth') {
                $apiResponse = $this->payTraceApiService->processPaymentAuthorize(
                    $token,
                    $amount,
                    $billingData,
                    $saveCard,
                    $salesChannelContext
                );
            } else {
                $apiResponse = $this->payTraceApiService->processPayment(
                    $token,
                    $amount,
                    $billingData,
                    $saveCard,
                    $salesChannelContext
                );
            }
        } else {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Missing payment information for order-first flow');
        }


        if ($apiResponse instanceof ResponseInterface) {
            $body = (string) $apiResponse->getBody();
            /** @var array<string,mixed>|null $decoded */
            $decoded = $body !== '' ? json_decode($body, true) : null;
            $apiResponse = is_array($decoded) ? $decoded : ['error' => true, 'message' => 'Invalid API response'];
        }

        $success = !((bool) ($apiResponse['error'] ?? true));

        if (!$success) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException((string) ($apiResponse['message'] ?? 'Payment failed in order-first flow'));
        }

        /** @var string|null $transactionId */
        $transactionId = isset($apiResponse['data']['transaction_id']) ? (string) $apiResponse['data']['transaction_id'] : null;

        if ($transactionId === null || $transactionId === '') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('No transaction ID returned from PayTrace');
        }

        $paymentMethod = $orderTransaction->getPaymentMethod();
        $paymentMethodName = $paymentMethod ? (string) $paymentMethod->getName() : '';
        if ($paymentMethodName === '') {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Payment method missing on transaction');
        }

        $this->payTraceTransactionService->addTransaction(
            (string) $order->getId(),
            $paymentMethodName,
            (string) $transactionId,
            $transactionStatus,
            $context
        );

        $this->transactionStateHandler->{$handlerMethodName}($transaction->getOrderTransactionId(), $context);
    }
}
