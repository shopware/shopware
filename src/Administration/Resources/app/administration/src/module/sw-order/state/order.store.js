export default {
    namespaced: true,

    state: {
        customer: null,
        cart: {
            token: null,
            lineItems: []
        }
    },

    mutations: {
        setCustomer(state, customer) {
            state.customer = customer;
        },

        removeCustomer(state) {
            state.customer = null;
        },

        setCartToken(state, token) {
            state.cart.token = token;
        },

        removeCartToken(state) {
            state.cart.token = null;
        },

        setCart(state, cart) {
            state.cart = cart;
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
            Shopware
                .Service('cartSalesChannelService')
                .createCart(salesChannelId)
                .then(response => {
                    commit('setCartToken', response.data['sw-context-token']);
                    dispatch('dispatchUpdateCustomerContext');
                });
        },

        dispatchUpdateCustomerContext({ state }) {
            const { customer, cart } = state;
            Shopware
                .Service('salesChannelContextService')
                .updateCustomerContext(customer.id, customer.salesChannelId, cart.token);
        },

        updateCustomerContext(_, { customerId, salesChannelId, contextToken }) {
            Shopware
                .Service('salesChannelContextService')
                .updateCustomerContext(customerId, salesChannelId, contextToken);
        },

        updateOrderContext(_, { context, salesChannelId, contextToken }) {
            Shopware
                .Service('salesChannelContextService')
                .updateContext(context, salesChannelId, contextToken);
        },

        saveOrder(_, { salesChannelId, contextToken }) {
            Shopware
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
                .then((response) => commit('setCart', response.data.data));
        }
    }
};
