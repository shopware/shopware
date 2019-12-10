import template from './sw-order-create-base.html.twig';

const { Component, State } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-create-base', {
    template,

    inject: ['repositoryFactory'],

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        defaultCriteria() {
            const criteria = new Criteria();
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('salesChannel')
                .addAssociation('defaultPaymentMethod')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags');

            return criteria;
        },

        orderDate() {
            const today = new Date();
            return Shopware.Utils.format.date(today);
        },

        customer() {
            return State.get('swOrder').customer || {};
        },

        isCustomerActive() {
            return State.getters['swOrder/isCustomerActive'];
        },

        cart() {
            return State.get('swOrder').cart;
        }
    },

    methods: {
        createCart() {
            State.dispatch('swOrder/createCart', { salesChannelId: this.customer.salesChannelId });
        },

        updateCustomerContext() {
            State.dispatch('swOrder/updateCustomerContext', {
                customerId: this.customer.id,
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token
            });
        },

        onSelectExistingCustomer(customerId) {
            this.customerRepository.get(
                customerId,
                Shopware.Context.api,
                this.defaultCriteria
            ).then((customer) => {
                State.dispatch('swOrder/selectExistingCustomer', { customer });

                if (this.cart.token === null) {
                    this.createCart();
                } else {
                    this.updateCustomerContext();
                }
            });
        },

        onAddNewCustomer() {
            // TODO: Handle function
        },

        onEditBillingAddress() {
            // TODO: Handle function
        },

        onEditShippingAddress() {
            // TODO: Handle function
        }
    }
});
