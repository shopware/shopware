import PaymentOverviewCardStore from '../state/overview-cards.store';

/**
 * @sw-package checkout
 */

Shopware.State.registerModule('paymentOverviewCardState', PaymentOverviewCardStore);

Shopware.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    Shopware.State.commit('paymentOverviewCardState/add', componentConfig);
});
