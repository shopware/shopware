import type { InAppPurchaseRequest } from '../../../store/in-app-purchase-checkout.store';
import template from './sw-in-app-purchase-checkout.html.twig';

/**
 * @sw-package checkout
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    computed: {
        entry(): InAppPurchaseRequest | null {
            const store = Shopware.Store.get('inAppPurchaseCheckout');
            return store.entry;
        },
    },

    methods: {
        closeModal() {
            const store = Shopware.Store.get('inAppPurchaseCheckout');
            store.dismiss();
        },
    },
});
