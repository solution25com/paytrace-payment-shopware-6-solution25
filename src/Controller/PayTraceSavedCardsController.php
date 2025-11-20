<?php

namespace solu1Paytrace\Controller;

use solu1Paytrace\Service\PayTraceApiService;
use solu1Paytrace\Service\PayTraceConfigService;
use solu1Paytrace\Service\PayTraceCustomerVaultService;
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
    private PayTraceApiService $payTraceApiService;
    private PayTraceCustomerVaultService $payTraceCustomerVaultService;
    private PaytraceConfigService $payTraceConfigService;

    public function __construct(PayTraceApiService $payTraceApiService, PayTraceCustomerVaultService $payTraceCustomerVaultService, PaytraceConfigService $payTraceConfigService)
    {
        $this->payTraceApiService = $payTraceApiService;
        $this->payTraceCustomerVaultService = $payTraceCustomerVaultService;
        $this->payTraceConfigService = $payTraceConfigService;
    }

    #[Route(path: '/account/payTrace-saved-cards', name: 'frontend.account.payTrace-saved-cards.page', methods: ['GET'])]
    public function savedCard(SalesChannelContext $context): Response
    {
        $customerId = $context->getCustomer()?->getId();

        if (!$customerId) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $customerVaultRecords = $this->payTraceCustomerVaultService->getCustomerVaultRecords($customerId, $context->getContext());

        $paymentToken = $this->payTraceApiService->generatePaymentToken($context->getSalesChannelId());
        $paymentToken = is_string($paymentToken) ? $paymentToken : "";
        $mode = $this->payTraceConfigService->getConfig('mode', $context->getSalesChannel()->getId());
        return $this->renderStorefront('@Storefront/storefront/page/account/payTrace-saved-cards.html.twig', [
        'savedCards' => $customerVaultRecords,
        'paymentToken' => $paymentToken,
        'mode' => strtolower($mode)
        ]);
    }

    #[Route(path: '/account/payTrace-saved-cards/add-card', name: 'frontend.account.payTrace-saved-cards-add-card.page', methods: ['POST'])]
    public function addCard(Request $request, SalesChannelContext $context): Response
    {
        $data = json_decode($request->getContent(), true);
        $customerId = $context->getCustomer()?->getId();

        if (!$customerId) {
            throw $this->createNotFoundException();
        }

        $customerLabel = $this->payTraceCustomerVaultService->getNextCardLabel($context);

        $data['cardCount'] = $customerLabel;
        $data['customerId'] = $customerId;

        $responseFromMethod = $this->payTraceApiService->createCustomerProfile($data, $context);

        if (is_array($responseFromMethod) && ($responseFromMethod['message'] ?? null) === 'Success') {
            $customerVaultId = $responseFromMethod['data']['customer_id'];
            $cardHolderName = $data['billing_address']['name'];

            try {
                $responseFromCustomerProfile = $this->payTraceApiService->getCustomerProfile($customerVaultId, $context);

                if ($responseFromCustomerProfile['message'] === 'Success') {
                    $masked = $responseFromCustomerProfile['data']['card_masked'];

                    $pos = strpos($masked, 'x');
                    if ($pos === false) {
                        throw new \RuntimeException("Invalid masked value: 'x' not found.");
                    }

                    $firstDigits = substr($masked, 0, $pos);

                    $lastDigits = substr($masked, -4);
                    $cardType = $this->payTraceCustomerVaultService->getCardType($firstDigits);

                    $this->payTraceCustomerVaultService->store(
                        $context,
                        $customerVaultId,
                        $cardHolderName,
                        $cardType,
                        $lastDigits,
                        $customerLabel
                    );
                }
            } catch (\Exception $exception) {
                return new JsonResponse([
                'message' => $exception->getMessage(),
                'success' => false,
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new JsonResponse([
            'success' => true,
            'message' => 'Card added successfully',
            ]);
        }

        $data = (array)$responseFromMethod;
        return new JsonResponse([
        'error' => true,
        'message' => $data['message'] ?? 'Unknown error',
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route(path: '/account/payTrace-saved-cards/delete-card/{vaultedCustomerId}', name: 'frontend.account.payTrace-saved-cards-delete-card.page', methods: ['POST'])]
    public function deleteCard(Request $request, SalesChannelContext $context): Response
    {
        $data = json_decode($request->getContent(), true);
        $customer = $context->getCustomer();

        if (!$customer || !isset($data['cardId'])) {
            return new JsonResponse([
            'error' => true,
            'message' => 'Missing required parameters: cardId or customerId.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $customerVaultId = $data['cardId'];
        $customerId = $customer->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('vaultedCustomerId', $customerVaultId));
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        $vaultedCard = $this->payTraceCustomerVaultService->getCustomerVaultRecords($customerId, $context->getContext(), $criteria);

        if (!$vaultedCard->count()) {
            return new JsonResponse([
            'error' => true,
            'message' => 'Unauthorized action.',
            ], Response::HTTP_FORBIDDEN);
        }

        $responseFromMethod = $this->payTraceApiService->deleteVaultedCard($customerVaultId, $context);

        if (is_array($responseFromMethod) && ($responseFromMethod['error'] ?? false)) {
            return new JsonResponse([
            'error' => true,
            'message' => $responseFromMethod['message'],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (is_array($responseFromMethod) && ($responseFromMethod['message'] ?? null) === 'Success') {
            $this->payTraceCustomerVaultService->delete($context, $customerVaultId);

            return $this->json([
            'success' => true,
            'message' => 'Card deleted successfully',
            ]);
        }

        return new JsonResponse([
        'error' => true,
        'message' => 'Unexpected error occurred while deleting the card',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function getMode(string $mode): string
    {
        if ($mode == 'live') {
            return 'live';
        }
        return 'sandbox';
    }
}
