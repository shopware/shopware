import template from './sw-order-create-base.html.twig';

const { Component, State, Utils, Data, Service } = Shopware;
const { Criteria } = Data;
const { get, format } = Utils;

Component.register('sw-order-create-base', {
    template,

    data() {
        return {
            isLoading: false,
            address: {
                data: null
            },
            showAddressModal: false
        };
    },

    computed: {
        customerRepository() {
            return Service('repositoryFactory').create('customer');
        },

        customerAddressRepository() {
            return Service('repositoryFactory').create('customer_address');
        },

        currencyRepository() {
            return Service('repositoryFactory').create('currency');
        },

        customerAddressCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('country');

            return criteria;
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

        onSelectExistingCustomer(customerId) {
            return this.customerRepository
                .get(customerId, Shopware.Context.api, this.defaultCriteria)
                .then((customer) => {
                    this.setCustomer(customer);

                    this.setCurrency(customer);

                    if (!this.cart.token) {
                        this.createCart();
                    }
                });
        },

        setCustomer(customer) {
            State.dispatch('swOrder/selectExistingCustomer', { customer });
        },

        setCurrency(customer) {
            this.currencyRepository.get(customer.salesChannel.currencyId, Shopware.Context.api).then((currency) => {
                State.commit('swOrder/setCurrency', currency);
            });
        },

        onEditBillingAddress() {
            const contextId = 'billingAddressId';
            const contextDataKey = 'billingAddress';
            const data = this.customer[contextDataKey]
                ? this.customer[contextDataKey]
                : this.customer.defaultBillingAddress;

            this.address = { contextId, contextDataKey, data };
            this.showAddressModal = true;
        },

        onEditShippingAddress() {
            const contextId = 'shippingAddressId';
            const contextDataKey = 'shippingAddress';
            const data = this.customer[contextDataKey]
                ? this.customer[contextDataKey]
                : this.customer.defaultShippingAddress;

            this.address = { contextId, contextDataKey, data };
            this.showAddressModal = true;
        },

        setCustomerAddress({ contextId, data }) {
            this.customer[contextId] = data.id;

            const availableCustomerAddresses = [
                {
                    id: this.customer.billingAddressId,
                    dataKey: 'billingAddress'
                },
                {
                    id: this.customer.shippingAddressId,
                    dataKey: 'shippingAddress'
                },
                {
                    id: this.customer.defaultBillingAddressId,
                    dataKey: 'defaultBillingAddress'
                },
                {
                    id: this.customer.defaultShippingAddressId,
                    dataKey: 'defaultShippingAddress'
                }
            ];

            this.customerAddressRepository
                .get(data.id, Shopware.Context.api, this.customerAddressCriteria)
                .then((updatedAddress) => {
                    availableCustomerAddresses.forEach((customerAddress) => {
                        if (customerAddress.id === data.id) {
                            this.customer[customerAddress.dataKey] = updatedAddress;
                        }
                    });
                });
        },

        closeModal() {
            this.showAddressModal = false;
            this.address.data = null;
        },

        save() {
            this.closeModal();
        },

        reset() {
            this.onSelectExistingCustomer(this.customer.id).finally(() => {
                this.closeModal();
            });
        },

        onSaveItem(item) {
            this.updateLoading(true);

            State.dispatch('swOrder/saveLineItem', {
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
