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
    name: 'sw-order-customer-address-select',
    load: () => import('./src/sw-order-customer-address-select'),
    public: {
        props: [
            'customer',
            'value',
            'sameAddressLabel',
            'sameAddressValue',
            'disabled',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_order_customer_address_select',
        ],
        computed: [
            'addressId',
            'isSameAddress',
            'addressRepository',
            'addressCriteria',
        ],
        methods: [
            'createdComponent',
            'getSelectionLabel',
            'getCustomerAddress',
            'getCustomerAddresses',
            'searchAddress',
            'searchAddressResults',
            'getDisplayedCustomerAddresses',
        ],
    },
});
