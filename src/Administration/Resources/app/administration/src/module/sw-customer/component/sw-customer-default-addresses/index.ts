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
    name: 'sw-customer-default-addresses',
    load: () => import('./src/sw-customer-default-addresses'),
    public: {
        props: [
            'customer',
            'customerEditMode',
        ],
        blocks: [
            'sw_customer_default_addresses',
            'sw_customer_default_addresses_shipping',
            'sw_customer_default_addresses_shipping_postal',
            'sw_customer_default_addresses_billing',
            'sw_customer_default_addresses_billing_postal',
        ],
        computed: [
            'defaultShippingAddressLink',
            'defaultBillingAddressLink',
        ],
        methods: [
            'createdComponent',
            'renderFormattingAddress',
        ],
    },
});
