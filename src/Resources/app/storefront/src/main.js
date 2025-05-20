import PayTraceCreditCardPlugin from './payTrace-payment-plugin/payTrace-credit-card.plugin';
import PayTraceAchECheckPlugin from "./payTrace-payment-plugin/payTrace-ach-eCheck.plugin";
import PayTraceSavedCardsPlugin from './payTrace-payment-plugin/payTrace-saved-cards.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('PayTraceCreditCardPlugin', PayTraceCreditCardPlugin, '[payTrace-payment-credit-card-plugin]');
PluginManager.register('PayTraceAchECheckPlugin', PayTraceAchECheckPlugin, '[payTrace-payment-ach-echeck-plugin]');
PluginManager.register('PayTraceSavedCardsPlugin', PayTraceSavedCardsPlugin, '[payTrace-saved-cards-plugin]');
