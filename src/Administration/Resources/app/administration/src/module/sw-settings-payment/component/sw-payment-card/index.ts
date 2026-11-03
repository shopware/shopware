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
    name: 'sw-payment-card',
    load: () => import('./src/sw-payment-card'),
    public: {
        props: [
            'paymentMethod',
        ],
        emits: [
            'set-payment-active',
        ],
        blocks: [
            'sw_payment_card',
            'sw_payment_card_preview',
            'sw_payment_card_description',
            'sw_payment_card_link',
            'sw_payment_card_toggle',
        ],
        computed: [
            'previewUrl',
        ],
        methods: [
            'setPaymentMethodActive',
        ],
    },
});
