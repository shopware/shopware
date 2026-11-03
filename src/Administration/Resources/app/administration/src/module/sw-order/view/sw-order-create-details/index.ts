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
    name: 'sw-order-create-details',
    load: () => import('./src/sw-order-create-details'),
    public: {
        emits: [
            'error',
        ],
        blocks: [
            'sw_order_create_details',
            'sw_order_create_details_promotion_modal',
            'sw_order_create_details_payment',
            'sw_order_create_details_shipping',
            'sw_order_create_details_order',
            'sw_order_create_details_email',
            'sw_order_create_details_phone_number',
            'sw_order_create_details__order_sales_channel',
            'sw_order_create_details_order_language',
            'sw_order_create_details_voucher_field',
            'sw_order_create_details_switch_disable_auto_promotion',
        ],
        computed: [
            'salesChannelId',
            'customer',
            'cart',
            'currency',
            'salesChannelContext',
            'email',
            'phoneNumber',
            'cartDelivery',
            'shippingCosts',
            'deliveryDate',
            'shippingMethodCriteria',
            'paymentMethodCriteria',
            'languageCriteria',
            'currencyCriteria',
            'currencyRepository',
            'isCartTokenAvailable',
            'hasLineItem',
            'promotionCodeLineItems',
            'disabledAutoPromotion',
            'promotionCodeTags',
        ],
        methods: [
            'createdComponent',
            'updateContext',
            'loadCart',
            'onRemoveExistingCode',
            'onRemoveItems',
            'updatePromotionList',
            'toggleAutomaticPromotions',
            'onClosePromotionModal',
            'onSavePromotionModal',
            'modifyShippingCosts',
            'handlePromotionCodeTags',
            'onSubmitCode',
        ],
    },
});
