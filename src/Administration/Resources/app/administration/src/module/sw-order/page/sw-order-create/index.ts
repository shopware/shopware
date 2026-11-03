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
    name: 'sw-order-create',
    load: () => import('./src/sw-order-create'),
    public: {
        blocks: [
            'sw_order_create',
            'sw_order_create_smart_bar_header',
            'sw_order_create_smart_bar_header_title',
            'sw_order_create_smart_bar_header_title_text',
            'sw_order_create_smart_bar_actions',
            'sw_order_create_smart_bar_actions_slot',
            'sw_order_create_smart_bar_actions_cancel',
            'sw_order_create_smart_bar_actions_add',
            'sw_order_create_language_switch',
            'sw_order_create_content',
            'sw_order_create_content_view',
            'sw_order_create_content_tabs',
            'sw_order_create_content_mt_tabs',
            'sw_order_create_content_tabs_general',
            'sw_order_create_content_tabs_details',
            'sw_order_create_invalid_promotion_modal',
            'sw_order_create_remind_payment_modal',
            'sw_order_create_remind_payment_modal_content',
            'sw_order_create_remind_payment_modal_footer',
        ],
        computed: [
            'customer',
            'cart',
            'invalidPromotionCodes',
            'isSaveOrderValid',
            'orderValidateErrorMessage',
            'paymentMethodRepository',
            'showInitialModal',
            'orderCreateTabs',
        ],
        methods: [
            'createdComponent',
            'redirectToOrderList',
            'saveFinish',
            'onSaveOrder',
            'fetchPaymentMethodName',
            'onCancelOrder',
            'showError',
            'openInvalidCodeModal',
            'closeInvalidCodeModal',
            'removeInvalidCode',
            'onRemindPaymentModalClose',
            'onRemindCustomer',
        ],
    },
});
