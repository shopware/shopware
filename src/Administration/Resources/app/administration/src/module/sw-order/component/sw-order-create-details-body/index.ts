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
    name: 'sw-order-create-details-body',
    load: () => import('./src/sw-order-create-details-body'),
    public: {
        props: [
            'customer',
            'isCustomerActive',
        ],
        emits: [
            'on-edit-billing-address',
            'on-edit-shipping-address',
        ],
        blocks: [
            'sw_order_create_details_body',
            'sw_order_create_details_body_email',
            'sw_order_create_details_body_billing_address',
            'sw_order_create_details_body_phone_number',
            'sw_order_create_details_body_shipping_address',
            'sw_order_create_details_body_shipping_address_identical',
            'sw_order_create_details_body_shipping_address_content',
        ],
        computed: [
            'email',
            'phoneNumber',
            'billingAddress',
            'shippingAddress',
            'isAddressIdentical',
        ],
        methods: [
            'onEditBillingAddress',
            'onEditShippingAddress',
        ],
    },
});
