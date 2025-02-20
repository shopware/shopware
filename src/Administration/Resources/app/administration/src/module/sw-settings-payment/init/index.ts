import '../store/overview-cards.store';

/**
 * @sw-package checkout
 */

Shopware.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    if (componentConfig.component === 'sw-card') {
        componentConfig.component = 'mt-card';
    }

    Shopware.Store.get('paymentOverviewCard').add(componentConfig);
});
