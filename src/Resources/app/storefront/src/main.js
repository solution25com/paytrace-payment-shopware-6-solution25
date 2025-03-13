import PayTraceCreditCardPlugin from './payTrace-payment-plugin/payTrace-credit-card.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('PayTraceCreditCardPlugin', PayTraceCreditCardPlugin, '[payTrace-payment-credit-card-plugin]');
