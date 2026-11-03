import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-in-app-purchase-checkout',
    load: () => import('./src/sw-in-app-purchase-checkout'),
    public: {
        blocks: [
            'sw_in_app_purchase_checkout_modal',
        ],
        computed: [
            'entry',
        ],
        methods: [
            'closeModal',
        ],
    },
});
