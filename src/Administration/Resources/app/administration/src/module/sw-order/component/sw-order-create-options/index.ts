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
    name: 'sw-order-create-options',
    load: () => import('./src/sw-order-create-options'),
    public: {
        props: [
            'promotionCodes',
            'disabledAutoPromotion',
            'sendOrderConfirmationMail',
            'context',
        ],
        emits: [
            'auto-promotion-toggle',
            'send-order-confirmation-mail-toggle',
            'promotions-change',
            'shipping-cost-change',
        ],
        blocks: [
            'sw_order_create_options',
            'sw_order_create_options_container',
            'sw_order_create_options_order',
            'sw_order_create_options_order_headline',
            'sw_order_create_options_order_switch_disable_auto_promotion',
            'sw_order_create_options_order_switch_send_order_confirmation_mail',
            'sw_order_create_options_order_field_container',
            'sw_order_create_options_order_language',
            'sw_order_create_options_promotions',
            'sw_order_create_options_payment',
            'sw_order_create_options_payment_headline',
            'sw_order_create_options_payment_container',
            'sw_order_create_options_payment_method',
            'sw_order_create_options_billing_address',
            'sw_order_create_options_order_currency',
            'sw_order_create_options_shipment',
            'sw_order_create_options_shipment_headline',
            'sw_order_create_options_shipping_method',
            'sw_order_create_options_shipping_address',
        ],
        computed: [
            'salesChannelId',
            'salesChannelCriteria',
            'shippingMethodCriteria',
            'paymentMethodCriteria',
            'customer',
            'currency',
            'cart',
            'cartDelivery',
        ],
        methods: [
            'updateSameAsBillingAddressToggle',
            'createdComponent',
            'validatePromotions',
            'onToggleAutoPromotion',
            'onToggleSendOrderConfirmationMail',
            'changePromotionCodes',
            'updateCartContext',
            'updateOrderContext',
            'loadCart',
            'onChangeShippingCost',
        ],
    },
});
