import '../store/overview-cards.store';

/**
 * @sw-package checkout
 */

Shopware.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    Shopware.Store.get('paymentOverviewCard').add(componentConfig);
});
