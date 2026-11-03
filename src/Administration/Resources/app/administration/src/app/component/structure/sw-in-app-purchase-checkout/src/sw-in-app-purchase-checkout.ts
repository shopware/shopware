import template from './sw-in-app-purchase-checkout.html.twig';
import { type InAppPurchaseRequest, useInAppPurchaseCheckoutStore, wrapComponentConfig } from '../dependencies';

/**
 * @sw-package checkout
 *
 * @private
 */
export default wrapComponentConfig({
    template,

    computed: {
        entry(): InAppPurchaseRequest | null {
            const store = useInAppPurchaseCheckoutStore();
            return store.entry;
        },
    },

    methods: {
        closeModal() {
            const store = useInAppPurchaseCheckoutStore();
            store.dismiss();
        },
    },
});
