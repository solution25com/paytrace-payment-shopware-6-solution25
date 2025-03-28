<?php declare(strict_types=1);

namespace PayTrace\Storefront\Controller;

use PayTrace\Service\PayTraceApiService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class PayTraceController extends StorefrontController
{
  private PayTraceApiService $payTraceApiService;

  public function __construct(PayTraceApiService $payTraceApiService)
  {
    $this->payTraceApiService = $payTraceApiService;
  }

  #[Route(
    path: '/capture',
    name: 'frontend.payTrace.capture',
    methods: ['POST']
  )]
  public function capture(Request $request, SalesChannelContext $context): JsonResponse
  {
    $data = json_decode($request->getContent(), true);

    if (empty($data)) {
      return new JsonResponse(['success' => false, 'message' => 'Missing payment token.'], JsonResponse::HTTP_BAD_REQUEST);
    }

    try {
      $paymentResponse = $this->payTraceApiService->processPayment($data['token'], $data['amount'], $context);

      if ($paymentResponse['status'] === 'success') {
        return new JsonResponse([
          'success' => true,
          'message' => 'Payment processed successfully.',
          'transactionId' => $paymentResponse['data']['transaction_id'],
        ], JsonResponse::HTTP_OK);
      } else {
        return new JsonResponse([
          'success' => false,
          'message' => 'Payment failed: ' . $paymentResponse['message']
        ], JsonResponse::HTTP_BAD_REQUEST);
      }

    } catch (\Exception $e) {
      return new JsonResponse([
        'success' => false,
        'message' => 'Payment processing failed due to an internal error.'
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route(
    path: '/vaulted-capture-paytrace',
    name: 'frontend.payTrace.vaultedCapture',
    methods: ['POST']
  )]
  public function vaultedCapture(Request $request, SalesChannelContext $context): JsonResponse
  {
    $data = json_decode($request->getContent(), true);

    if (empty($data)) {
      return new JsonResponse(['success' => false, 'message' => 'Missing Data .'], JsonResponse::HTTP_BAD_REQUEST);
    }

    try {
      $paymentResponse = $this->payTraceApiService->processVaultedPayment($data);

      if ($paymentResponse['status'] === 'success') {
        return new JsonResponse([
          'success' => true,
          'message' => 'Payment processed successfully.',
          'transactionId' => $paymentResponse['data']['transaction_id'],
        ], JsonResponse::HTTP_OK);
      } else {
        return new JsonResponse([
          'success' => false,
          'message' => 'Payment failed: ' . $paymentResponse['message']
        ], JsonResponse::HTTP_BAD_REQUEST);
      }

    } catch (\Exception $e) {
      return new JsonResponse([
        'success' => false,
        'message' => 'Payment processing failed due to an internal error.'
      ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

}
