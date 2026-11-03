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
    name: 'sw-order-create-promotion-modal',
    load: () => import('./src/sw-order-create-promotion-modal'),
    public: {
        props: [
            'currency',
            'salesChannelId',
        ],
        emits: [
            'close',
            'save',
        ],
        blocks: [
            'sw_order_create_promotion_modal',
            'sw_order_create_promotion_modal_body',
            'sw_order_create_promotion_modal_message',
            'sw_order_create_promotion_modal_promotions',
            'sw_order_create_promotion_modal_promotion_name',
            'sw_order_create_promotion_modal_promotion_description',
            'sw_order_create_promotion_modal_footer',
            'sw_order_create_promotion_modal_footer_button_cancel',
            'sw_order_create_promotion_modal_footer_button_save',
        ],
        computed: [
            'cart',
            'cartAutomaticPromotionItems',
            'hasNoAutomaticPromotions',
        ],
        methods: [
            'onCancel',
            'onSave',
            'disableAutomaticPromotions',
            'getDescription',
        ],
    },
});
