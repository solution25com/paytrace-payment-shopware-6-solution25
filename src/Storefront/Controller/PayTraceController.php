<?php

declare(strict_types=1);

namespace solu1Paytrace\Storefront\Controller;

use solu1Paytrace\Library\Constants\ValidatorUtility;
use solu1Paytrace\Service\PayTraceApiService;
use solu1Paytrace\Service\PayTraceConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
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
    private ValidatorUtility $validator;
    private LoggerInterface $logger;

    public function __construct(
        PayTraceApiService $payTraceApiService,
        PayTraceConfigService $payTraceConfigService,
        ValidatorUtility $validator,
        LoggerInterface $logger
    ) {
        $this->payTraceApiService   = $payTraceApiService;
        $this->payTraceConfigService = $payTraceConfigService;
        $this->validator            = $validator;
        $this->logger               = $logger;
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
            'amount' => [new Assert\NotBlank(), new Assert\Type('string')],
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
            $paymentResponse = $this->payTraceApiService->processEcheckDeposit($data, $billingData, $context->getSalesChannelId());
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
            'amount' => [new Assert\NotBlank(), new Assert\Type('string')],
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
            /** @var array<string,mixed> $paymentResponse */
            $paymentResponse = $this->processPayment(
                $data['token'],
                $data['amount'],
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
        $data = $request->request->all();

        $constraints = new Assert\Collection([
            'selectedCardVaultedId' => [new Assert\NotBlank(), new Assert\Type('string')],
            'amount' => [new Assert\NotBlank(), new Assert\Type('string')],
        ]);

        $errors = $this->validator->validateFields($data, $constraints);
        if (count($errors) > 0) {
            return $this->createJsonResponse(false, 'Missing data.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentResponse = $this->payTraceApiService->processVaultedPayment($data, $context);
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
}
