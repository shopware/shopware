import template from './sw-order-create-details-header.html.twig';

const { Component } = Shopware;

Component.register('sw-order-create-details-header', {
    template,

    props: {
        customer: {
            type: Object,
            default: {}
        },

        orderDate: {
            type: String,
            required: true
        },

        cartPrice: {
            type: Object,
            default: null
        },

        currency: {
            type: Object,
            default: null
        }
    },

    data() {
        return {
            customerId: '',
            showNewCustomerModal: false
        };
    },

    methods: {
        onSelectExistingCustomer(customerId) {
            this.$emit('on-select-existing-customer', customerId);
        },

        onShowNewCustomerModal() {
            this.showNewCustomerModal = true;
            this.$emit('on-show-new-customer-modal');
        },

        onCloseNewCustomerModal() {
            this.showNewCustomerModal = false;
        }
    }
});
