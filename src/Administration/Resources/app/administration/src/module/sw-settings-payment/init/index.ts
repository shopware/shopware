import '../store/overview-cards.store';
import usePaymentOverviewCardStore from 'shopware:stores/paymentOverviewCard';

/**
 * @sw-package checkout
 */

Shopware.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    if (componentConfig.component === 'sw-card') {
        componentConfig.component = 'mt-card';
    }

    usePaymentOverviewCardStore().add(componentConfig);
});
