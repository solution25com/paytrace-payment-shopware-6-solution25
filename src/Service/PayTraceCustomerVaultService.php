<?php

namespace PayTrace\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayTraceCustomerVaultService
{
  private EntityRepository $vaultedCustomerRepository;
  private LoggerInterface $logger;

  public function __construct(EntityRepository $vaultedShopperRepository, LoggerInterface $logger)
  {
    $this->vaultedCustomerRepository = $vaultedShopperRepository;
    $this->logger = $logger;
  }

  public function store(SalesChannelContext $salesChannelContext,
                        string $vaultedShopperId,
                        string $cardHolderName,
                        string $cardType,
                        string $lastDigits,
                        string $customerLabel): void
  {
    $context = $salesChannelContext->getContext();
    $salesChannelCustomerId = $salesChannelContext->getCustomer()->getId();

    try {
      $existingShopper = $this->vaultedCustomerRepository->search(
        (new Criteria())->addFilter(new EqualsFilter('customerId', $salesChannelCustomerId)), $context
      )->getEntities();

      if (!empty($existingShopper)) {
        $this->vaultedCustomerRepository->create(
          [
            [
              'id' => Uuid::randomHex(),
              'customerId' => $salesChannelCustomerId,
              'vaultedCustomerId' => $vaultedShopperId,
              'cardHolderName' => $cardHolderName,
              'cardType' => $cardType,
              'lastDigits' => $lastDigits,
              'customerLabel' => $customerLabel,
              'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]
          ],
          $context
        );
      } else {
        $this->vaultedCustomerRepository->create(
          [
            [
              'id' => Uuid::randomHex(),
              'customerId' => $salesChannelCustomerId,
              'vaultedCustomerId' => $vaultedShopperId,
              'cardHolderName' => $cardHolderName,
              'cardType' => $cardType,
              'lastDigits' => $lastDigits,
              'customerLabel' => $customerLabel,
              'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]
          ],
          $context
        );
      }
    } catch (\Exception $e) {
      $this->logger->error('Error storing vaulted shopper data: ' . $e->getMessage());
    }
  }

  public function delete(SalesChannelContext $salesChannelContext, string $customerVaultId): void
  {
    $context = $salesChannelContext->getContext();

    try {
      $criteria = new Criteria();
      $criteria->addFilter(new EqualsFilter('vaultedCustomerId', $customerVaultId));
      $existingShopper = $this->vaultedCustomerRepository->search($criteria, $context)->first();

      if ($existingShopper) {
        $this->vaultedCustomerRepository->delete([['id' => $existingShopper->getId()]], $context);
        $this->logger->info('Successfully deleted vaulted customer data from the database.');
      } else {
        $this->logger->warning('No vaulted customer data found to delete for vault ID: ' . $customerVaultId);
      }
    } catch (\Exception $e) {
      $this->logger->error('Error deleting vaulted customer data: ' . $e->getMessage());
    }
  }

  public function countCustomerVaultRecords(SalesChannelContext $context, string $customerId): int
  {
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('customerId', $customerId));

    $count = $this->vaultedCustomerRepository->search($criteria, $context->getContext())->getTotal();

    return $count;
  }

  public function dropdownCards(SalesChannelContext $context, string $customerId)
  {
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('customerId', $customerId));

    $savedCards = $this->vaultedCustomerRepository->search($criteria, $context->getContext())->getElements();

    $formattedCards = [];

    foreach ($savedCards as $card) {
      $formattedCards[] = [
        'vaultedCustomerId' => $card->getVaultedCustomerId(),
        'customerLabel' => $card->getCustomerLabel(),
        'lastDigits' => $card->getLastDigits(),
        'cardType' => $card->getCardType(),
      ];
    }

    return $formattedCards;
  }

  function getCardType(string $cardNumber): string {
    $cardNumber = preg_replace('/\D/', '', $cardNumber); // Remove non-digits

    if (preg_match('/^4[0-9]{0,}$/', $cardNumber)) {
      return 'Visa';
    }

    if (preg_match('/^5[1-5][0-9]{0,}$/', $cardNumber)) {
      return 'MasterCard';
    }

    if (preg_match('/^3[47][0-9]{0,}$/', $cardNumber)) {
      return 'American Express';
    }

    if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{0,}$/', $cardNumber)) {
      return 'Discover';
    }

    if (preg_match('/^35(?:2[89]|[3-8][0-9])[0-9]{0,}$/', $cardNumber)) {
      return 'JCB';
    }

    if (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{0,}$/', $cardNumber)) {
      return 'Diners Club';
    }

    return 'Unknown';
  }

}