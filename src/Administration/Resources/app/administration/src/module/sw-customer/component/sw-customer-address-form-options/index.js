import template from './sw-customer-address-form-options.html.twig';

const { Component } = Shopware;

Component.register('sw-customer-address-form-options', {
    template,

    props: {
        customer: {
            type: Object,
            required: true
        },

        address: {
            type: Object,
            required: true,
            default: {}
        },

        customFieldSets: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            isDefaultShippingAddressId: false,
            isDefaultBillingAddressId: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isDefaultShippingAddressId = this.customer.defaultShippingAddressId === this.address.id;
            this.isDefaultBillingAddressId = this.customer.defaultBillingAddressId === this.address.id;
        },

        onChangeDefaultShippingAddress(active) {
            this.$emit('default-address-change', { name: 'shipping-address', id: this.address.id, value: active });
        },

        onChangeDefaultBillingAddress(active) {
            this.$emit('default-address-change', { name: 'billing-address', id: this.address.id, value: active });
        }
    }

});
