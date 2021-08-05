import template from './sw-order-create-details-body.html.twig';

const { Component } = Shopware;

Component.register('sw-order-create-details-body', {
    template,

    props: {
        // FIXME: add required attribute and or default value
        // eslint-disable-next-line vue/require-default-prop
        customer: {
            type: Object,
        },

        isCustomerActive: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        email: {
            get() {
                return this.customer ? this.customer.email : null;
            },

            set(email) {
                if (this.customer) this.customer.email = email;
            },
        },

        phoneNumber: {
            get() {
                return this.customer ? this.customer.defaultBillingAddress.phoneNumber : null;
            },

            set(phoneNumber) {
                if (this.customer) this.customer.defaultBillingAddress.phoneNumber = phoneNumber;
            },
        },

        billingAddress: {
            get() {
                if (this.customer) {
                    if (this.customer.billingAddress) {
                        return this.customer.billingAddress;
                    }

                    return this.customer.defaultBillingAddress;
                }

                return null;
            },

            set(billingAddress) {
                if (this.customer) this.customer.billingAddress = billingAddress;
            },
        },

        shippingAddress: {
            get() {
                if (this.customer) {
                    if (this.customer.shippingAddress) {
                        return this.customer.shippingAddress;
                    }

                    return this.customer.defaultShippingAddress;
                }

                return null;
            },

            set(shippingAddress) {
                if (this.customer) this.customer.shippingAddress = shippingAddress;
            },
        },

        isAddressIdentical() {
            return this.shippingAddress.id === this.billingAddress.id;
        },
    },

    methods: {
        onEditBillingAddress() {
            this.$emit('on-edit-billing-address');
        },

        onEditShippingAddress() {
            this.$emit('on-edit-shipping-address');
        },
    },
});
