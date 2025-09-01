<?php


namespace PayTrace\Subscriber;

use PayTrace\Gateways\AchEcheck;
use PayTrace\Gateways\CreditCard;
use PayTrace\Service\PayTraceApiService;
use PayTrace\Service\PayTraceConfigService;
use PayTrace\Service\PayTraceCustomerVaultService;
use PayTrace\Storefront\Struct\CheckoutTemplateCustomData;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;


class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
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

  public static function getSubscribedEvents(): array
  {
    return [
      CheckoutConfirmPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields',
    ];
  }

  public function addPaymentMethodSpecificFormFields(CheckoutConfirmPageLoadedEvent $event): void
  {
    $pageObject = $event->getPage();
    $amount = $pageObject->getCart()->getPrice()->getTotalPrice();
    $salesChannelContext = $event->getSalesChannelContext();
    $mode = $this->getMode(
      $this->payTraceConfigService->getConfig('mode', $salesChannelContext->getSalesChannel()->getId())
    );

    $selectedPaymentGateway = $salesChannelContext->getPaymentMethod();
    $templateVariables = new CheckoutTemplateCustomData();

    if ($selectedPaymentGateway->getHandlerIdentifier() == CreditCard::class) {
      $clientKeyResult = $this->payTraceApiService->generatePaymentToken($salesChannelContext->getSalesChannel()->getId());

      $clientKey = is_string($clientKeyResult) ? $clientKeyResult : null;
      $isGuest = $salesChannelContext->getCustomer()?->getGuest() ?? null;

      $customerId = $salesChannelContext->getCustomer()->getId();
      $cardsDropdown = $this->payTraceCustomerVaultService->dropdownCards($salesChannelContext, $customerId);

      $templateVariables->assign([
        'template' => '@Storefront/payTrace-payment/credit-card.html.twig',
        'isGuest' => $isGuest,
        'gateway' => 'creditCard',
        'amount' => $amount,
        'clientKey' => $clientKey,
        'cardsDropdown' => json_encode($cardsDropdown),
        'mode' => $mode,
      ]);
    }

    if ($selectedPaymentGateway->getHandlerIdentifier() == AchEcheck::class) {
      $templateVariables->assign([
        'template' => '@Storefront/payTrace-payment/ach-eCheck.html.twig',
        'gateway' => 'achEcheck',
        'amount' => $amount,
        'mode' => $mode,
      ]);
    }

    $pageObject->addExtension(
      CheckoutTemplateCustomData::EXTENSION_NAME,
      $templateVariables
    );
  }

  public function getMode(string $mode): string
  {
    if($mode == 'live'){
      return 'live';
    }
    return 'sandbox';
  }
}
