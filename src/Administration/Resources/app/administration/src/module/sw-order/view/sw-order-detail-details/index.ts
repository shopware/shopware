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
    name: 'sw-order-detail-details',
    load: () => import('./src/sw-order-detail-details'),
    public: {
        props: [
            'orderId',
            'isSaveSuccessful',
        ],
        emits: [
            'update-loading',
            'save-and-recalculate',
            'save-and-reload',
            'save-edits',
            'reload-entity-data',
            'error',
        ],
        blocks: [
            'sw_order_detail_details',
            'sw_order_detail_details_payment',
            'sw_order_detail_details_payment_billing_address',
            'sw_order_detail_details_payment_billing_address_divider',
            'sw_order_detail_details_payment_method_select',
            'sw_order_detail_details_shipping',
            'sw_order_detail_details_shipping_address',
            'sw_order_detail_details_shipping_address_divider',
            'sw_order_detail_details_shipping_method_select',
            'sw_order_detail_details_shipping_costs',
            'sw_order_detail_details_shipping_date',
            'sw_order_detail_details_shipping_tracking_codes',
            'sw_order_detail_details_order',
            'sw_order_detail_details_order_email',
            'sw_order_detail_details_order_phone_number',
            'sw_order_detail_details_order_sales_channel',
            'sw_order_detail_details_order_language',
            'sw_order_detail_details_order_affiliate',
            'sw_order_detail_details_order_campaign',
            'sw_order_detail_details_order_customer_comment',
            'sw_order_detail_details_order_internal_comment',
            'sw_order_detail_details_order_promotion',
            'sw_order_detail_details_custom_fields',
            'sw_order_detail_base_state_history_modal',
        ],
        computed: [
            'isLoading',
            'order',
            'versionContext',
            'orderAddressIds',
            'delivery',
            'transaction',
            'customFieldSetRepository',
            'customFieldSetCriteria',
            'salesChannelCriteria',
            'paymentMethodCriteria',
            'taxStatus',
            'currency',
            'billingAddress',
            'shippingAddress',
            'selectedBillingAddressId',
            'selectedShippingAddressId',
            'shippingCosts',
        ],
        methods: [
            'createdComponent',
            'onShippingChargeEdited',
            'loadingChange',
            'saveAndRecalculate',
            'saveAndReload',
            'onSaveEdits',
            'reloadEntityData',
            'showError',
            'updateLoading',
            'validateTrackingCode',
            'onChangeOrderAddress',
        ],
    },
});
