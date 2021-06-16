import template from './sw-customer-base-form.html.twig';
import './sw-customer-base-form.scss';
import errorConfig from '../../error-config.json';

const { Component } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-base-form', {
    template,

    inject: ['feature'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('customer', errorConfig['sw.customer.detail.base'].customer),
    },

    watch: {
        'customer.guest'(newVal) {
            if (newVal) {
                this.customer.password = null;
            }
        },
    },

    methods: {
        onSalesChannelChange(salesChannelId) {
            this.$emit('sales-channel-change', salesChannelId);
        },
    },
});
