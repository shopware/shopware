import PaymentOverviewCardStore from '../state/overview-cards.store';

Shopware.State.registerModule('paymentOverviewCardState', PaymentOverviewCardStore);

Shopware.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    Shopware.State.commit('paymentOverviewCardState/add', componentConfig);
});
