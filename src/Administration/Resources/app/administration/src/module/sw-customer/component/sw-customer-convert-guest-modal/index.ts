import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-customer-convert-guest-modal',
    load: () => import('./src/sw-customer-convert-guest-modal'),
    public: {
        props: [
            'customer',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_customer_guest_convert_customer_modal',
            'sw_customer_guest_convert_customer_modal_content',
            'sw_customer_guest_convert_customer_modal_option_reset',
            'sw_customer_guest_convert_customer_modal_option_manual',
        ],
        methods: [
            'sendRecoveryEmail',
            'onCancel',
            'convert',
            'handleConvertErrors',
        ],
    },
});
