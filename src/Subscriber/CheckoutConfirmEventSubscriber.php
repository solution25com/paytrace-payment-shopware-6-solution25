<?php


namespace PayTrace\Subscriber;

use PayTrace\Gateways\AchEcheck;
use PayTrace\Gateways\CreditCard;
use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceCustomerVaultService;
use PayTrace\Storefront\Struct\CheckoutTemplateCustomData;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;


class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
  private PayTraceApiService $payTraceApiService;
  private PayTraceCustomerVaultService $payTraceCustomerVaultService;

  public function __construct(PayTraceApiService $payTraceApiService, PayTraceCustomerVaultService $payTraceCustomerVaultService)
  {
    $this->payTraceApiService = $payTraceApiService;
    $this->payTraceCustomerVaultService = $payTraceCustomerVaultService;
  }

  public static function getSubscribedEvents(): array
  {
    return [
      CheckoutConfirmPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields',
    ];
  }

  public function addPaymentMethodSpecificFormFields(CheckoutConfirmPageLoadedEvent $event): void
  {

    $clientKey = $this->payTraceApiService->generatePaymentToken();
    $pageObject = $event->getPage();
    $amount = $pageObject->getCart()->getPrice()->getTotalPrice();
    $salesChannelContext = $event->getSalesChannelContext();
    $selectedPaymentGateway = $salesChannelContext->getPaymentMethod();
    $isGuest = $salesChannelContext->getCustomer()->getGuest();
    $templateVariables = new CheckoutTemplateCustomData();
    if ($selectedPaymentGateway->getHandlerIdentifier() == CreditCard::class) {

      $customerId = $salesChannelContext->getCustomer()->getId();
      $cardsDropdown = $this->payTraceCustomerVaultService->dropdownCards($salesChannelContext,$customerId);

      $templateVariables->assign([
        'template' => '@Storefront/payTrace-payment/credit-card.html.twig',
        'isGuest' => $isGuest,
        'gateway' => 'creditCard',
        'amount' => $amount,
        'clientKey' => $clientKey,
        'cardsDropdown' => json_encode($cardsDropdown),
      ]);

      $pageObject->addExtension(
        CheckoutTemplateCustomData::EXTENSION_NAME,
        $templateVariables
      );
    }
      if ($selectedPaymentGateway->getHandlerIdentifier() == AchEcheck::class) {

          $templateVariables->assign([
              'template' => '@Storefront/payTrace-payment/ach-eCheck.html.twig',
              'gateway' => 'achEcheck',
              'amount' => $amount,
          ]);

          $pageObject->addExtension(
              CheckoutTemplateCustomData::EXTENSION_NAME,
              $templateVariables
          );
      }
  }

}