import { Component } from 'src/core/shopware';
import template from './sw-customer-address-form-options.html.twig';

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

        attributeSets: {
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
        this.isDefaultShippingAddressId = this.customer.defaultShippingAddressId === this.address.id;
        this.isDefaultBillingAddressId = this.customer.defaultBillingAddressId === this.address.id;
    },
    methods: {
        onChangeDefaultShippingAddress(active) {
            this.$emit('change-default-address', { name: 'shipping-address', id: this.address.id, value: active });
        },

        onChangeDefaultBillingAddress(active) {
            this.$emit('change-default-address', { name: 'billing-address', id: this.address.id, value: active });
        }
    }

});
