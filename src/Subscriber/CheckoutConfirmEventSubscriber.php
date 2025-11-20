<?php

namespace solu1Paytrace\Subscriber;

use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use solu1Paytrace\Gateways\AchEcheck;
use solu1Paytrace\Gateways\CreditCard;
use solu1Paytrace\Service\PayTraceApiService;
use solu1Paytrace\Service\PayTraceConfigService;
use solu1Paytrace\Service\PayTraceCustomerVaultService;
use solu1Paytrace\Storefront\Struct\CheckoutTemplateCustomData;
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
            AccountEditOrderPageLoadedEvent::class => 'accountEditOrderPageLoadedEvent',
        ];
    }

    public function addPaymentMethodSpecificFormFields(CheckoutConfirmPageLoadedEvent $event): void
    {
        $this->checkConfirmOrEditPage($event);
    }

    public function accountEditOrderPageLoadedEvent(AccountEditOrderPageLoadedEvent $event): void
    {
        $this->checkConfirmOrEditPage($event);
    }

    /**
     * @param mixed $event This parameter can accept any type of data.
     */
    public function checkConfirmOrEditPage($event): void
    {
        $pageObject = $event->getPage();

        if ($event instanceof AccountEditOrderPageLoadedEvent) {
            $amount = $pageObject->getOrder()->getAmountTotal();
        } else {
            $amount = $pageObject->getCart()->getPrice()->getTotalPrice();
        }
        $pageObject = $event->getPage();
        $salesChannelContext = $event->getSalesChannelContext();
        $mode = $this->getMode(
            $this->payTraceConfigService->getConfig('mode', $salesChannelContext->getSalesChannel()->getId())
        );
        $flow = $this->payTraceConfigService->getConfig('flow', $salesChannelContext->getSalesChannel()->getId());

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
                'flow' => $flow,
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
                'flow' => $flow,
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
        if ($mode == 'live') {
            return 'live';
        }
        return 'sandbox';
    }
}
