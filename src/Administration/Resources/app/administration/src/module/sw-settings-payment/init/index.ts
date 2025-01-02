import '../store/overview-cards.store';

/**
 * @package checkout
 */

Shopware.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    Shopware.Store.get('paymentOverviewCard').add(componentConfig);
});
