<?php declare(strict_types=1);

namespace PayTrace\Storefront\Controller;

use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Http\Message\ResponseInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class PayTraceController extends StorefrontController
{
  private PayTraceApiService $payTraceApiService;
  private PayTraceConfigService $payTraceConfigService;
  private LoggerInterface $logger;

  public function __construct(
    PayTraceApiService $payTraceApiService,
    PayTraceConfigService $payTraceConfigService,
    LoggerInterface $logger)
  {
    $this->payTraceApiService = $payTraceApiService;
    $this->payTraceConfigService = $payTraceConfigService;
    $this->logger = $logger;
  }

  private function processPayment(array $token, string $amount, array $billingData, bool $authAndCapture, SalesChannelContext $context): array | ResponseInterface {
    if ($authAndCapture) {
      return $this->payTraceApiService->processPaymentAuthorize($token, $amount, $billingData);
    }

    return $this->payTraceApiService->processPayment($token, $amount, $billingData);
  }


  #[Route(path: '/process-echeck-deposit', name: 'frontend.payTrace.process-echeck-deposit', methods: ['POST'])]
  public function processEcheckDeposit(Request $request, SalesChannelContext $context): JsonResponse
  {
    $data = json_decode($request->getContent(), true);

    if (empty($data)) {
      return $this->createJsonResponse(false, 'Missing data.', JsonResponse::HTTP_BAD_REQUEST);
    }

    try {
      $paymentResponse = $this->payTraceApiService->processEcheckDeposit($data);

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


  #[Route(path: '/capture-paytrace', name: 'frontend.payTrace.capture', methods: ['POST'])]
  public function capture(Request $request, SalesChannelContext $context): JsonResponse
  {
    $authAndCapture = $this->payTraceConfigService->getConfig('authorizeAndCapture');
    $data = json_decode($request->getContent(), true);
    $customer = $context->getCustomer();

    if (empty($data)) {
      return $this->createJsonResponse(false, 'Missing payment token.', JsonResponse::HTTP_BAD_REQUEST);
    }

    if (!$customer) {
      return $this->createJsonResponse(false, 'Missing customer.', JsonResponse::HTTP_BAD_REQUEST);
    }

    $billingAddress = $customer->getActiveBillingAddress();
    $country = $billingAddress?->getCountry();
    $state = $billingAddress?->getCountryState();

    $customerData = [
      'fullName' => $customer->getFirstName() . ' ' . $customer->getLastName(),
      'city' => $billingAddress?->getCity(),
      'country' => $country?->getIso(),
      'state' => $state && strpos($state->getShortCode(), '-') !== false
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
    $data = json_decode($request->getContent(), true);

    if (empty($data)) {
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
    if ($paymentResponse['status'] === 'success') {
      return $this->createJsonResponse(
        true,
        'Payment processed successfully.',
        JsonResponse::HTTP_OK,
        ['transactionId' => $paymentResponse['data']['transaction_id']]
      );
    }

    return $this->createJsonResponse(
      false,
      'Payment failed: ' . $paymentResponse['message'],
      JsonResponse::HTTP_BAD_REQUEST
    );
  }


  private function createJsonResponse(bool $success, string $message, int $statusCode, array $data = []): JsonResponse
  {
    return new JsonResponse(array_merge(['success' => $success, 'message' => $message], $data), $statusCode);
  }
}

