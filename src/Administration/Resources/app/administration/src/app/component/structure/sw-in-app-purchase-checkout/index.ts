import type { InAppPurchaseRequest } from '../../../store/in-app-purchase-checkout.store';
import template from './sw-in-app-purchase-checkout.html.twig';
import useInAppPurchaseCheckoutStore from 'shopware:stores/inAppPurchaseCheckout';

/**
 * @sw-package checkout
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
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
