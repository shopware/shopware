import template from './sw-order-create-details-body.html.twig';

const { Component } = Shopware;

Component.register('sw-order-create-details-body', {
    template,

    props: {
        customer: {
            type: Object,
            default: {}
        },

        isCustomerActive: {
            type: Boolean,
            default: false
        }
    },

    computed: {
        email: {
            get() {
                return this.customer ? this.customer.email : null;
            },

            set(email) {
                if (this.customer) this.customer.email = email;
            }
        },

        phoneNumber: {
            get() {
                return this.customer ? this.customer.defaultBillingAddress.phoneNumber : null;
            },

            set(phoneNumber) {
                if (this.customer) this.customer.defaultBillingAddress.phoneNumber = phoneNumber;
            }
        }
    },

    methods: {
        onEditBillingAddress() {
            this.$emit('on-edit-billing-address');
        },

        onEditShippingAddress() {
            this.$emit('on-edit-shipping-address');
        }
    }
});
