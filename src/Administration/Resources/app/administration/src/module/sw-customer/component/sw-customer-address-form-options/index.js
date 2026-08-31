import template from './sw-customer-address-form-options.html.twig';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['default-address-change'],

    props: {
        customer: {
            type: Object,
            required: true,
        },

        address: {
            type: Object,
            required: true,
            default: () => {},
        },

        customFieldSets: {
            type: Array,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isDefaultShippingAddressId: false,
            isDefaultBillingAddressId: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isDefaultShippingAddressId = this.isDefaultAddress(this.customer.defaultShippingAddressId);
            this.isDefaultBillingAddressId = this.isDefaultAddress(this.customer.defaultBillingAddressId);
        },

        isDefaultAddress(defaultAddressId) {
            if (!defaultAddressId) {
                return false;
            }

            if (defaultAddressId === this.address.id) {
                return true;
            }

            // Order addresses are snapshots with their own id; match them to the default by content hash.
            if (this.customer.addresses?.has(this.address.id)) {
                return false;
            }

            const defaultAddress = this.customer.addresses?.get(defaultAddressId);

            return Boolean(this.address.hash && defaultAddress?.hash === this.address.hash);
        },

        onChangeDefaultShippingAddress(active) {
            this.$emit('default-address-change', {
                name: 'shipping-address',
                id: this.address.id,
                value: active,
            });
        },

        onChangeDefaultBillingAddress(active) {
            this.$emit('default-address-change', {
                name: 'billing-address',
                id: this.address.id,
                value: active,
            });
        },
    },
};
