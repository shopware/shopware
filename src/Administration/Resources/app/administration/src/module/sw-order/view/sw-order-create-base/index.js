import template from './sw-order-create-base.html.twig';

const { Component, State, Utils, Data } = Shopware;
const { Criteria } = Data;
const { get, format } = Utils;

Component.register('sw-order-create-base', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false
        };
    },

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
            return format.date(today);
        },

        customer() {
            return State.get('swOrder').customer;
        },

        isCustomerActive() {
            return State.getters['swOrder/isCustomerActive'];
        },

        cart() {
            return State.get('swOrder').cart;
        },

        cartLineItems() {
            return this.cart.lineItems;
        },

        cartPrice() {
            return this.cart.price;
        },

        currency() {
            return State.get('swOrder').currency;
        },

        cartDelivery() {
            return get(this.cart, 'deliveries[0]', null);
        },

        filteredCalculatedTaxes() {
            if (!this.cartPrice || !this.cartPrice.calculatedTaxes) {
                return [];
            }

            return this.sortByTaxRate(this.cartPrice.calculatedTaxes).filter(price => price.tax !== 0);
        }
    },

    methods: {
        createCart() {
            State.dispatch('swOrder/createCart', { salesChannelId: this.customer.salesChannelId });
        },

        updateCustomerContext() {
            if (this.customer === null) return;

            State.dispatch('swOrder/updateCustomerContext', {
                customerId: this.customer.id,
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token
            }).then(() => {
                if (this.cart.token === null) return;

                this.updateLoading(true);

                State.dispatch('swOrder/getCart', {
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token
                })
                    .finally(() => this.updateLoading(false));
            });
        },

        onSelectExistingCustomer(customerId) {
            this.customerRepository
                .get(customerId, Shopware.Context.api, this.defaultCriteria)
                .then((customer) => {
                    State.dispatch('swOrder/selectExistingCustomer', { customer });

                    if (this.cart.token === null) {
                        this.createCart();
                    } else {
                        this.updateCustomerContext();
                    }
                });
        },

        onEditBillingAddress() {
            // TODO: Handle function
        },

        onEditShippingAddress() {
            // TODO: Handle function
        },

        onAddProductItem(item) {
            this.updateLoading(true);

            State.dispatch('swOrder/addProductItem', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
                productId: item.identifier,
                quantity: item.quantity
            })
                .finally(() => this.updateLoading(false));
        },

        onAddCustomItem(item) {
            this.updateLoading(true);

            State.dispatch('swOrder/addCustomItem', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
                item
            })
                .finally(() => this.updateLoading(false));
        },

        onEditItem(item) {
            this.updateLoading(true);

            State.dispatch('swOrder/updateLineItem', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
                item
            })
                .finally(() => this.updateLoading(false));
        },

        onRemoveItems(lineItemKeys) {
            this.updateLoading(true);

            State.dispatch('swOrder/removeLineItems', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
                lineItemKeys: lineItemKeys
            })
                .finally(() => this.updateLoading(false));
        },

        updateLoading(loadingValue) {
            this.isLoading = loadingValue;
        },

        sortByTaxRate(price) {
            return price.sort((prev, current) => {
                return prev.taxRate - current.taxRate;
            });
        }
    }
});
