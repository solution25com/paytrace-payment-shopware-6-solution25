import PayTraceCreditCardPlugin from './payTrace-payment-plugin/payTrace-credit-card.plugin';
import PayTraceAchECheckPlugin from "./payTrace-payment-plugin/payTrace-ach-eCheck.plugin";

const PluginManager = window.PluginManager;
PluginManager.register('PayTraceCreditCardPlugin', PayTraceCreditCardPlugin, '[payTrace-payment-credit-card-plugin]');
PluginManager.register('PayTraceAchECheckPlugin', PayTraceAchECheckPlugin, '[payTrace-payment-ach-echeck-plugin]');
