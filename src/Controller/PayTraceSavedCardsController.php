<?php

namespace PayTrace\Controller;

use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceCustomerVaultService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class PayTraceSavedCardsController extends StorefrontController
{
  private EntityRepository $customerVaultRepository;
  private PayTraceApiService $payTraceApiService;
  private PayTraceCustomerVaultService $payTraceCustomerVaultService;

  public function __construct(EntityRepository $customerVaultRepository, PayTraceApiService $payTraceApiService, PayTraceCustomerVaultService $payTraceCustomerVaultService)
  {
    $this->customerVaultRepository = $customerVaultRepository;
    $this->payTraceApiService = $payTraceApiService;
    $this->payTraceCustomerVaultService = $payTraceCustomerVaultService;
  }

  #[Route(path: '/account/payTrace-saved-cards', name: 'frontend.account.payTrace-saved-cards.page', methods: ['GET'])]
  public function savedCard(SalesChannelContext $context): Response
  {
    $customerId = $context->getCustomer()?->getId();

    if (!$customerId) {
      return $this->redirectToRoute('frontend.account.login.page');
    }

    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('customerId', $customerId));

    $customerVaultRecords = $this->customerVaultRepository->search($criteria, $context->getContext())->getEntities();

    $paymentToken = $this->payTraceApiService->generatePaymentToken();

    return $this->renderStorefront('@Storefront/storefront/page/account/payTrace-saved-cards.html.twig', [
      'savedCards' => $customerVaultRecords,
      'paymentToken' => $paymentToken,
    ]);
  }


  #[Route(path: '/account/payTrace-saved-cards/add-card', name: 'frontend.account.payTrace-saved-cards-add-card.page', methods: ['POST'])]
  public function addCard(Request $request, SalesChannelContext $context): Response
  {
    $data = json_decode($request->getContent(), true);
    $customerId = $context->getCustomer()?->getId();

    $countCustomer = $this->payTraceCustomerVaultService->countCustomerVaultRecords($context, $customerId);
    $customerLabel = '_Card_' . ($countCustomer + 1);

    $data['cardCount'] = $customerLabel;
    $data['customerId'] = $customerId;

    $responseFromMethod = $this->payTraceApiService->createCustomerProfile($data);

    if ($responseFromMethod['message'] === 'Success') {
      $customerVaultId = $responseFromMethod['data']['customer_id'];
      $cardHolderName = $data['billing_address']['name'];

      try{
        $responseFromCustomerProfile = $this->payTraceApiService->getCustomerProfile($customerVaultId);

        if($responseFromCustomerProfile['message'] === 'Success'){
          $masked = $responseFromCustomerProfile['data']['card_masked'];
          $firstDigits = substr($masked, 0, strpos($masked, 'x'));

          $lastDigits =  '**** - **** - **** -' .substr($masked,-4);
          $cardType = $this->payTraceCustomerVaultService->getCardType($firstDigits);

          $this->payTraceCustomerVaultService->store(
            $context,
            $customerVaultId,
            $cardHolderName,
            $cardType,
            $lastDigits,
            $customerId . $customerLabel
          );

        }
      }catch (\Exception $exception){
        return new JsonResponse([
          'message' => $exception->getMessage(),
          'success'=> false,
        ]);

      }

      return $this->json([
        'success' => true,
        'message' => 'Card added successfully',
      ]);
    }

    return $this->json([
      'error' => true,
      'message' => $responseFromMethod['message'] ?? 'Unknown error',
    ]);
  }

  #[Route(path: '/account/payTrace-saved-cards/delete-card/{vaultedCustomerId}', name: 'frontend.account.payTrace-saved-cards-delete-card.page', methods: ['POST'])]
  public function deleteCard(Request $request, SalesChannelContext $context): Response
  {
    $data = json_decode($request->getContent(), true);
    $customer = $context->getCustomer();

    if (!$customer || !isset($data['cardId'])) {
      return $this->json([
        'error' => true,
        'message' => 'Missing required parameters: cardId or customerId.',
      ], Response::HTTP_BAD_REQUEST);
    }

    $customerVaultId = $data['cardId'];
    $customerId = $customer->getId();

    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('vaultedCustomerId', $customerVaultId));
    $criteria->addFilter(new EqualsFilter('customerId', $customerId));

    $vaultedCard = $this->customerVaultRepository->search($criteria, $context->getContext())->first();

    if (!$vaultedCard) {
      return $this->json([
        'error' => true,
        'message' => 'Unauthorized action.',
      ], Response::HTTP_FORBIDDEN);
    }

    $responseFromMethod = $this->payTraceApiService->deleteVaultedCard($customerVaultId);

    if (isset($responseFromMethod['error']) && $responseFromMethod['error']) {
      return $this->json([
        'error' => true,
        'message' => $responseFromMethod['message'],
      ]);
    }

    if (isset($responseFromMethod['message']) && $responseFromMethod['message'] === 'Success') {
      $this->payTraceCustomerVaultService->delete($context, $customerVaultId);

      return $this->json([
        'success' => true,
        'message' => 'Card deleted successfully',
      ]);
    }

    return $this->json([
      'error' => true,
      'message' => 'Unexpected error occurred while deleting the card',
    ]);
  }


}
