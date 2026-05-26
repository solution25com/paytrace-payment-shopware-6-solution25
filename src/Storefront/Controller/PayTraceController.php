<?php

declare(strict_types=1);

namespace solu1Paytrace\Storefront\Controller;

use solu1Paytrace\Library\Constants\ValidatorUtility;
use solu1Paytrace\Service\PayTraceApiService;
use solu1Paytrace\Service\PayTraceConfigService;
use solu1Paytrace\Service\PayTraceCustomerVaultService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class PayTraceController extends StorefrontController
{
    private PayTraceApiService $payTraceApiService;
    private PayTraceConfigService $payTraceConfigService;
    private PayTraceCustomerVaultService $payTraceCustomerVaultService;
    private ValidatorUtility $validator;
    private LoggerInterface $logger;
    private CartService $cartService;

    public function __construct(
        PayTraceApiService $payTraceApiService,
        PayTraceConfigService $payTraceConfigService,
        PayTraceCustomerVaultService $payTraceCustomerVaultService,
        ValidatorUtility $validator,
        LoggerInterface $logger,
        CartService $cartService
    ) {
        $this->payTraceApiService   = $payTraceApiService;
        $this->payTraceConfigService = $payTraceConfigService;
        $this->payTraceCustomerVaultService = $payTraceCustomerVaultService;
        $this->validator            = $validator;
        $this->logger               = $logger;
        $this->cartService          = $cartService;
    }

    /**
     * @param array{hpf_token:string, enc_key:string} $token
     * @param array<string, string|null> $billingData
     * @return array<string, mixed>
     */
    private function processPayment(
        array $token,
        string $amount,
        array $billingData,
        bool $saveCard,
        string $authAndCapture,
        SalesChannelContext $context
    ): array {
        if ($authAndCapture === 'auth') {
            /** @var array<string,mixed> $result */
            $result = (array) $this->payTraceApiService->processPaymentAuthorize($token, $amount, $billingData, $saveCard, $context);
            return $result;
        }

        /** @var array<string,mixed> $result */
        $result = (array) $this->payTraceApiService->processPayment($token, $amount, $billingData, $saveCard, $context);
        return $result;
    }

    #[Route(path: '/process-echeck-deposit', name: 'frontend.payTrace.process-echeck-deposit', methods: ['POST'])]
    public function processEcheckDeposit(Request $request, Cart $cart, SalesChannelContext $context): JsonResponse
    {
        $data = $request->request->all();
        $customer = $context->getCustomer();

        if (!$customer) {
            throw $this->createNotFoundException();
        }

        $constraints = new Assert\Collection([
            'billingName' => [new Assert\NotBlank(), new Assert\Type('string')],
            'routingNumber' => [new Assert\NotBlank(), new Assert\Type('string')],
            'accountNumber' => [new Assert\NotBlank(), new Assert\Type('string')],
            'accountType' => [new Assert\NotBlank(), new Assert\Type('string')],
            'amount' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);

        $errors = $this->validator->validateFields($data, $constraints);
        if (count($errors) > 0) {
            return $this->createJsonResponse(false, 'Missing data.', Response::HTTP_BAD_REQUEST);
        }

        $billingAddress = $customer->getActiveBillingAddress();
        $country = $billingAddress?->getCountry();
        $state = $billingAddress?->getCountryState();

        $billingData = [
            'city' => $billingAddress?->getCity(),
            'country' => $country?->getIso(),
            'state' => $state && str_contains($state->getShortCode(), '-')
                ? explode('-', $state->getShortCode())[1]
                : null,
            'street' => $billingAddress?->getStreet(),
            'street2' => $billingAddress?->getAdditionalAddressLine1(),
            'zip' => $billingAddress?->getZipcode(),
            'email' => $customer->getEmail(),
        ];

        try {
            $authoritativeAmount = $this->resolveAuthoritativeAmount($context, $cart);
            if ($authoritativeAmount === null) {
                return $this->createJsonResponse(false, 'Could not resolve authoritative cart amount.', Response::HTTP_BAD_REQUEST);
            }

            $payload = $data;
            $payload['amount'] = $authoritativeAmount;

            $paymentResponse = $this->payTraceApiService->processEcheckDeposit($payload, $billingData, $context->getSalesChannelId());
            return $this->handlePaymentResponse($paymentResponse);
        } catch (\Exception $e) {
            $this->logger->error('Vaulted payment processing failed: ' . $e->getMessage());

            return $this->createJsonResponse(
                false,
                'Payment processing failed due to an internal error.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route(path: '/capture-paytrace', name: 'frontend.payTrace.capture', methods: ['POST'])]
    public function capture(Request $request, SalesChannelContext $context): JsonResponse
    {
        $authAndCapture = $this->payTraceConfigService->getConfig('authorizeAndCapture');
        $customer = $context->getCustomer();

        if (!$customer) {
            throw $this->createNotFoundException();
        }

        $data = $request->request->all();
        $saveCard = $data['saveCard'] ?? false;

        $constraints = new Assert\Collection([
            'token' => new Assert\Collection([
                'hpf_token' => [new Assert\NotBlank(), new Assert\Type('string')],
                'enc_key' => [new Assert\NotBlank(), new Assert\Type('string')],
            ]),
            'amount' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
            'saveCard' => [new Assert\Optional(), new Assert\Type(['type' => 'bool'])],
        ]);

        $errors = $this->validator->validateFields($data, $constraints);
        if (count($errors) > 0) {
            return new JsonResponse([
                "errors" => true,
                'message' => 'Missing Data'
            ], Response::HTTP_BAD_REQUEST);
        }

        $billingAddress = $customer->getActiveBillingAddress();
        $country = $billingAddress?->getCountry();
        $state = $billingAddress?->getCountryState();

        $customerData = [
            'fullName' => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'city' => $billingAddress?->getCity(),
            'country' => $country?->getIso(),
            'state' => $state && str_contains($state->getShortCode(), '-')
                ? explode('-', $state->getShortCode())[1]
                : null,
            'street' => $billingAddress?->getStreet(),
            'street2' => $billingAddress?->getAdditionalAddressLine1(),
            'zip' => $billingAddress?->getZipcode(),
            'email' => $customer->getEmail(),
        ];

        try {
            $authoritativeAmount = $this->resolveAuthoritativeAmount($context);
            if ($authoritativeAmount === null) {
                return $this->createJsonResponse(false, 'Could not resolve authoritative cart amount.', Response::HTTP_BAD_REQUEST);
            }

            /** @var array<string,mixed> $paymentResponse */
            $paymentResponse = $this->processPayment(
                $data['token'],
                $authoritativeAmount,
                $customerData,
                $saveCard,
                $authAndCapture,
                $context
            );

            return $this->handlePaymentResponse($paymentResponse);
        } catch (\Exception $e) {
            $this->logger->error('Payment processing failed: ' . $e->getMessage());
            return $this->createJsonResponse(
                false,
                'Payment processing failed due to an internal error.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route(path: '/vaulted-capture-paytrace', name: 'frontend.payTrace.vaultedCapture', methods: ['POST'])]
    public function vaultedCapture(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customer = $context->getCustomer();
        if ($customer === null || $customer->getGuest()) {
            return $this->createJsonResponse(false, 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->request->all();

        $constraints = new Assert\Collection([
            'selectedCardVaultedId' => [new Assert\NotBlank(), new Assert\Type('string')],
            'amount' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);

        $errors = $this->validator->validateFields($data, $constraints);
        if (count($errors) > 0) {
            return $this->createJsonResponse(false, 'Missing data.', Response::HTTP_BAD_REQUEST);
        }

        if (
            !$this->payTraceCustomerVaultService->isVaultedIdOwnedByCustomer(
                (string) $data['selectedCardVaultedId'],
                (string) $customer->getId(),
                $context->getContext()
            )
        ) {
            return $this->createJsonResponse(false, 'Vaulted card access denied.', Response::HTTP_FORBIDDEN);
        }

        try {
            $authoritativeAmount = $this->resolveAuthoritativeAmount($context);
            if ($authoritativeAmount === null) {
                return $this->createJsonResponse(false, 'Could not resolve authoritative cart amount.', Response::HTTP_BAD_REQUEST);
            }

            $payload = $data;
            $payload['amount'] = $authoritativeAmount;

            $paymentResponse = $this->payTraceApiService->processVaultedPayment($payload, $context);
            return $this->handlePaymentResponse((array)$paymentResponse);
        } catch (\Exception $e) {
            $this->logger->error('Vaulted payment processing failed: ' . $e->getMessage());
            return $this->createJsonResponse(
                false,
                'Payment processing failed due to an internal error.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @param array<string, mixed> $paymentResponse
     */
    private function handlePaymentResponse(array $paymentResponse): JsonResponse
    {
        $error = $paymentResponse['error'] ?? null;
        $message = strtolower(trim($paymentResponse['message'] ?? ''));

        if ($error === true) {
            return $this->createJsonResponse(
                false,
                'Payment failed: ' . ($paymentResponse['message'] ?? 'Unknown error'),
                Response::HTTP_BAD_REQUEST
            );
        }

        // skipped is in case when ACH is creating transaction but still return an error as a response
        if ($error === false && ($message === 'success' || $message === 'skipped')) {
            return $this->createJsonResponse(
                true,
                'Payment processed successfully.',
                Response::HTTP_OK,
                ['transactionId' => $paymentResponse['data']['transaction_id'] ?? null]
            );
        }

        return $this->createJsonResponse(
            false,
            'Unexpected payment response format.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(bool $success, string $message, int $statusCode, array $data = []): JsonResponse
    {
        return new JsonResponse(array_merge(['success' => $success, 'message' => $message], $data), $statusCode);
    }

    private function resolveAuthoritativeAmount(SalesChannelContext $context, ?Cart $cart = null): ?string
    {
        try {
            $resolvedCart = $cart ?? $this->cartService->getCart($context->getToken(), $context, true, true);
            $amount = $resolvedCart->getPrice()->getTotalPrice();
            if (!\is_numeric($amount)) {
                return null;
            }

            return number_format((float) $amount, 2, '.', '');
        } catch (\Throwable $e) {
            $this->logger->error('Could not resolve authoritative cart amount: ' . $e->getMessage());
            return null;
        }
    }
}
