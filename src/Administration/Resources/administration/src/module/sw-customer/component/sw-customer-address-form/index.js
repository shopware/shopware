import { Component } from 'src/core/shopware';
import template from './sw-customer-address-form.html.twig';
import './sw-customer-address-form.scss';

Component.register('sw-customer-address-form', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        },

        address: {
            type: Object,
            required: true,
            default: {}
        },

        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
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
