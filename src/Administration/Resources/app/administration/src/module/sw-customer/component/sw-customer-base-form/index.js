import template from './sw-customer-base-form.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-base-form', {
    template,

    props: {
        customer: {
            type: Object,
            required: true
        }
    },

    computed: {
        ...mapPropertyErrors('customer', [
            'salutationId',
            'firstName',
            'lastName',
            'email',
            'groupId',
            'salesChannelId',
            'defaultPaymentMethodId',
            'customerNumber',
            'password'
        ])
    },

    watch: {
        'customer.guest'(newVal) {
            if (newVal) {
                this.customer.password = null;
            }
        }
    },

    methods: {
        onSalesChannelChange(salesChannelId) {
            this.$emit('sales-channel-change', salesChannelId);
        }
    }
});
