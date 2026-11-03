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
    name: 'sw-order-create-initial-modal',
    load: () => import('./src/sw-order-create-initial-modal'),
    public: {
        emits: [
            'modal-close',
            'order-preview',
        ],
        blocks: [
            'sw_order_create_modal',
            'sw_order_create_modal_tabs',
            'sw_order_create_modal_mt_tabs',
            'sw_order_create_modal_mt_tabs_content',
            'sw_order_create_modal_mt_tabs_content_customer',
            'sw_order_create_modal_mt_tabs_content_products',
            'sw_order_create_modal_mt_tabs_content_options',
            'sw_order_create_modal_tabs_customer',
            'sw_order_create_modal_tabs_products',
            'sw_order_create_modal_tabs_options',
            'sw_order_create_modal_tabs_extension',
            'sw_order_create_modal_tabs_content',
            'sw_order_create_modal_tabs_content_customer',
            'sw_order_create_modal_tabs_content_products',
            'sw_order_create_modal_tabs_content_options',
            'sw_order_create_modal_footer_modal_footer_cancel',
            'sw_order_create_modal_footer_modal_footer_preview_order',
        ],
        computed: [
            'salesChannelId',
            'salesChannelContext',
            'currency',
            'cart',
            'customer',
            'isCustomerActive',
            'promotionCodeItems',
            'cartDelivery',
            'orderCreateInitialModalTabs',
        ],
        methods: [
            'onCloseModal',
            'onPreviewOrder',
            'onSaveItem',
            'addPromotionCodes',
            'updatePromotion',
            'onRemoveItems',
            'updateAutoPromotionToggle',
            'updateSendOrderConfirmationMail',
            'updateShippingCost',
            'updateOrderContext',
            'disableAutoAppliedPromotions',
            'modifyShippingCost',
            'cancelCart',
        ],
    },
});
