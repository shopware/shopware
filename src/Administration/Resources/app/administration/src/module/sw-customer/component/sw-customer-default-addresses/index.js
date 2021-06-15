import template from './sw-customer-default-addresses.html.twig';
import './sw-customer-default-addresses.scss';

const { Component } = Shopware;

Component.register('sw-customer-default-addresses', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
        },

        customerEditMode: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        defaultShippingAddressLink() {
            return {
                name: 'sw.customer.detail.addresses',
                params: {
                    id: this.customer.id,
                },
                query: {
                    detailId: this.customer.defaultShippingAddress.id,
                    edit: this.customerEditMode,
                },
            };
        },

        defaultBillingAddressLink() {
            return {
                name: 'sw.customer.detail.addresses',
                params: {
                    id: this.customer.id,
                },
                query: {
                    detailId: this.customer.defaultBillingAddress.id,
                    edit: this.customerEditMode,
                },
            };
        },
    },
});
