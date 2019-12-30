export default {
    namespaced: true,

    state() {
        return {
            customer: null,
            cart: {
                token: null,
                lineItems: []
            }
        };
    },

    mutations: {
        setCustomer(state, customer) {
            state.customer = customer;
        },

        setCartToken(state, token) {
            state.cart.token = token;
        },

        setCart(state, cart) {
            state.cart = cart;
            state.cart.lineItems = cart.lineItems.slice().reverse();
        }
    },

    getters: {
        isCustomerActive(state) {
            return state.customer && state.customer.active;
        }
    },

    actions: {
        selectExistingCustomer({ commit }, { customer }) {
            commit('setCustomer', customer);
        },

        createCart({ commit, dispatch }, { salesChannelId }) {
            return Shopware
                .Service('cartSalesChannelService')
                .createCart(salesChannelId)
                .then(response => {
                    commit('setCartToken', response.data['sw-context-token']);
                    dispatch('dispatchUpdateCustomerContext');
                });
        },

        getCart({ commit }, { salesChannelId, contextToken }) {
            return Shopware
                .Service('cartSalesChannelService')
                .getCart(salesChannelId, contextToken)
                .then((response) => commit('setCart', response.data.data));
        },

        dispatchUpdateCustomerContext({ state }) {
            const { customer, cart } = state;
            return Shopware
                .Service('salesChannelContextService')
                .updateCustomerContext(customer.id, customer.salesChannelId, cart.token);
        },

        updateCustomerContext(_, { customerId, salesChannelId, contextToken }) {
            return Shopware
                .Service('salesChannelContextService')
                .updateCustomerContext(customerId, salesChannelId, contextToken);
        },

        updateOrderContext(_, { context, salesChannelId, contextToken }) {
            return Shopware
                .Service('salesChannelContextService')
                .updateContext(context, salesChannelId, contextToken);
        },

        saveOrder(_, { salesChannelId, contextToken }) {
            return Shopware
                .Service('checkOutSalesChannelService')
                .checkout(salesChannelId, contextToken);
        },

        cancelOrder() {
            // TODO: Handle order data
            setTimeout(() => true, 1000);
        },

        addProductItem({ commit }, { salesChannelId, contextToken, productId, quantity }) {
            return Shopware
                .Service('cartSalesChannelService')
                .addProduct(salesChannelId, contextToken, productId, quantity)
                .then(response => commit('setCart', response.data.data));
        },

        removeLineItem({ dispatch }, { salesChannelId, contextToken, lineItemKeys }) {
            const deletionPromises = lineItemKeys.map((lineItemKey) => {
                return Shopware.Service('cartSalesChannelService').removeLineItem(salesChannelId, contextToken, lineItemKey);
            });

            return Promise.all(deletionPromises).then(() => {
                dispatch('getCart', { salesChannelId, contextToken });
            });
        },

        updateLineItem({ commit }, { salesChannelId, contextToken, lineItemKey, quantity }) {
            return Shopware
                .Service('cartSalesChannelService')
                .updateLineItem(salesChannelId, contextToken, lineItemKey, quantity)
                .then((response) => commit('setCart', response.data.data));
        }
    }
};
