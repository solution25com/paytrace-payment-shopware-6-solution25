<?php declare(strict_types=1);

namespace PayTrace\Storefront\Controller;

use PayTrace\Library\Constants\ValidatorUtility;
use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Http\Message\ResponseInterface;
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
    LoggerInterface $logger)
  {
    $this->payTraceApiService = $payTraceApiService;
    $this->payTraceConfigService = $payTraceConfigService;
    $this->validator = $validator;
    $this->logger = $logger;
  }

  private function processPayment(array $token, string $amount, array $billingData, string $authAndCapture, SalesChannelContext $context): array  {
    if ($authAndCapture == 'auth') {
      return $this->payTraceApiService->processPaymentAuthorize($token, $amount, $billingData);
    }
    return $this->payTraceApiService->processPayment($token, $amount, $billingData);
  }


  #[Route(path: '/process-echeck-deposit', name: 'frontend.payTrace.process-echeck-deposit', methods: ['POST'])]
  public function processEcheckDeposit(Request $request, Cart $cart, SalesChannelContext $context): JsonResponse
  {
    $data = $request->request->all();
    $customer = $context->getCustomer();

    if (!$customer) {
      return $this->createJsonResponse(false, 'Missing customer.', JsonResponse::HTTP_BAD_REQUEST);
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
      return $this->createJsonResponse(false, 'Missing data.', JsonResponse::HTTP_BAD_REQUEST);
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
      $paymentResponse = $this->payTraceApiService->processEcheckDeposit($data, $billingData);

      return $this->handlePaymentResponse($paymentResponse);
    } catch (\Exception $e) {
      $this->logger->error('Vaulted payment processing failed: ' . $e->getMessage());
      return $this->createJsonResponse(
        false,
        'Payment processing failed due to an internal error.',
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }


  #[Route(path: '/capture-paytrace', name: 'frontend.payTrace.capture', methods: ['POST'])]
  public function capture(Request $request, SalesChannelContext $context): JsonResponse
  {
    $authAndCapture = $this->payTraceConfigService->getConfig('authorizeAndCapture');
    $customer = $context->getCustomer();

    if (!$customer) {
      return $this->createJsonResponse(false, 'Missing customer.', JsonResponse::HTTP_BAD_REQUEST);
    }

    $data = $request->request->all();

    $constraints = new Assert\Collection([
        'token' => new Assert\Collection([
            'hpf_token' => [new Assert\NotBlank(), new Assert\Type('string')],
            'enc_key' => [new Assert\NotBlank(), new Assert\Type('string')],
        ]),
        'amount' => [new Assert\NotBlank(), new Assert\Type('string')],
    ]);

    $errors = $this->validator->validateFields($data, $constraints);
    if (count($errors) > 0) {
      return $this->createJsonResponse(false, 'Missing data.', JsonResponse::HTTP_BAD_REQUEST);
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

      $paymentResponse = $this->processPayment($data['token'], $data['amount'], $customerData, $authAndCapture, $context);
      return $this->handlePaymentResponse($paymentResponse);

    } catch (\Exception $e) {
      $this->logger->error('Payment processing failed: ' . $e->getMessage());
      return $this->createJsonResponse(
        false,
        'Payment processing failed due to an internal error.',
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
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
      return $this->createJsonResponse(false, 'Missing data.', JsonResponse::HTTP_BAD_REQUEST);
    }

    try {

      $paymentResponse = $this->payTraceApiService->processVaultedPayment($data);
      return $this->handlePaymentResponse((array) $paymentResponse);

    } catch (\Exception $e) {
      $this->logger->error('Vaulted payment processing failed: ' . $e->getMessage());
      return $this->createJsonResponse(
        false,
        'Payment processing failed due to an internal error.',
        JsonResponse::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }
  private function handlePaymentResponse(array $paymentResponse): JsonResponse
  {
    $error = $paymentResponse['error'] ?? null;
    $message = strtolower(trim($paymentResponse['message'] ?? ''));

    if ($error === true) {
      return $this->createJsonResponse(
        false,
        'Payment failed: ' . ($paymentResponse['message'] ?? 'Unknown error'),
        JsonResponse::HTTP_BAD_REQUEST
      );
    }

    // skipped is in case when ACH is creating transaction but still return an error as a response
    if ($error === false && ($message === 'success' || $message === 'skipped')) {
      return $this->createJsonResponse(
        true,
        'Payment processed successfully.',
        JsonResponse::HTTP_OK,
        ['transactionId' => $paymentResponse['data']['transaction_id'] ?? null]
      );
    }

    return $this->createJsonResponse(
      false,
      'Unexpected payment response format.',
      JsonResponse::HTTP_INTERNAL_SERVER_ERROR
    );
  }

  // TODO:create specific handlerResponse for customer Profile

  private function createJsonResponse(bool $success, string $message, int $statusCode, array $data = []): JsonResponse
  {
    return new JsonResponse(array_merge(['success' => $success, 'message' => $message], $data), $statusCode);
  }
}

