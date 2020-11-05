import template from './sw-order-create-details-header.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

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
        },
        customerCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('defaultBillingAddress.country');

            return criteria;
        }
    },

    methods: {
        onSelectExistingCustomer(customerId) {
            // Keep the current value when user tries to delete it
            if (!customerId) {
                return;
            }

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
