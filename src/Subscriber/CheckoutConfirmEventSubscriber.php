<?php


namespace PayTrace\Subscriber;

use PayTrace\Gateways\CreditCard;
use PayTrace\Service\PayTraceApiService;
use PayTrace\Storefront\Struct\CheckoutTemplateCustomData;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\HttpFoundation\Response;


class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
  private EntityRepository $customerRepository;
  private PayTraceApiService $payTraceApiService;

  public function __construct(EntityRepository $customerRepository, PayTraceApiService $payTraceApiService)
  {
    $this->customerRepository = $customerRepository;
    $this->payTraceApiService = $payTraceApiService;
  }

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents(): array
  {
    return [
      CheckoutConfirmPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields',
    ];
  }

  public function addPaymentMethodSpecificFormFields(CheckoutConfirmPageLoadedEvent $event): void
  {

    $clientKey = $this->payTraceApiService->generatePaymentToken();
    $context = $event->getContext();
    $pageObject = $event->getPage();
    $amount = $pageObject->getCart()->getPrice()->getTotalPrice();
    $salesChannelContext = $event->getSalesChannelContext();
    $selectedPaymentGateway = $salesChannelContext->getPaymentMethod();
    $isGuest = $salesChannelContext->getCustomer()->getGuest();
    $templateVariables = new CheckoutTemplateCustomData();
    if ($selectedPaymentGateway->getHandlerIdentifier() == CreditCard::class) {

      $customerId = $salesChannelContext->getCustomer()->getId();

      $templateVariables->assign([
        'template' => '@Storefront/payTrace-payment/credit-card.html.twig',
        'isGuest' => $isGuest,
        'gateway' => 'creditCard',
        'amount' => $amount,
        'clientKey' => $clientKey
      ]);



      $pageObject->addExtension(
        CheckoutTemplateCustomData::EXTENSION_NAME,
        $templateVariables
      );
    }

  }

}