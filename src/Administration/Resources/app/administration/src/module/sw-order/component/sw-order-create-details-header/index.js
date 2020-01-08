import template from './sw-order-create-details-header.html.twig';

const { Component } = Shopware;

Component.register('sw-order-create-details-header', {
    template,

    props: {
        customer: {
            type: Object
        },

        orderDate: {
            type: String,
            required: true
        },

        cartPrice: {
            type: Object
        },

        currency: {
            type: Object
        }
    },

    data() {
        return {
            showNewCustomerModal: false
        };
    },

    computed: {
        customerId: {
            get() {
                return this.customer ? this.customer.id : '';
            },

            set(customerId) {
                if (this.customer) this.customer.id = customerId;
            }
        }
    },

    methods: {
        onSelectExistingCustomer(customerId) {
            this.$emit('on-select-existing-customer', customerId);
        },

        onShowNewCustomerModal() {
            this.showNewCustomerModal = true;
        },

        onCloseNewCustomerModal() {
            this.showNewCustomerModal = false;
        }
    }
});
