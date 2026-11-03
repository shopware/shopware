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
    name: 'sw-customer-address-form-options',
    load: () => import('./src/sw-customer-address-form-options'),
    public: {
        props: [
            'customer',
            'address',
            'customFieldSets',
            'disabled',
        ],
        emits: [
            'default-address-change',
        ],
        blocks: [
            'sw_customer_address_form_options',
            'sw_customer_address_form_options_default_shipping_address',
            'sw_customer_address_form_options_default_billing_address',
            'sw_customer_address_form_options_custom_field_sets',
        ],
        methods: [
            'createdComponent',
            'isDefaultAddress',
            'onChangeDefaultShippingAddress',
            'onChangeDefaultBillingAddress',
        ],
    },
});
