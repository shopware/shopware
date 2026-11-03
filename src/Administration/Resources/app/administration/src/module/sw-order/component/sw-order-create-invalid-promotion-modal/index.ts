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
    name: 'sw-order-create-invalid-promotion-modal',
    load: () => import('./src/sw-order-create-invalid-promotion-modal'),
    public: {
        emits: [
            'close',
            'confirm',
        ],
        blocks: [
            'sw_order_create_invalid_promotion_modal',
            'sw_order_create_invalid_promotion_modal_description',
            'sw_order_create_invalid_promotion_modal_lists',
            'sw_order_create_invalid_promotion_modal_actions',
            'sw_order_create_invalid_promotion_modal_actions_confirm',
        ],
        computed: [
            'invalidPromotionCodes',
        ],
        methods: [
            'onClose',
            'onConfirm',
        ],
    },
});
