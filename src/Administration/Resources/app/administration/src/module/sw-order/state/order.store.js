export default {
    namespaced: true,

    state: {
        customer: null,
        cart: {
            token: null
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
        }
    },

    getters: {
        isCustomerActive(state) {
            if (state.customer) {
                return state.customer && state.customer.active;
            }
            return false;
        }
    },

    actions: {
        selectExistingCustomer({ commit }, { customer }) {
            commit('setCustomer', customer);
        },

        createCart({ commit }, { salesChannelId }) {
            Shopware
                .Service('cartSalesChannelService')
                .createCart(salesChannelId)
                .then((response) => commit('setCartToken', response.data['sw-context-token']));
        },

        updateCustomerContext(_, { customerId, salesChannelId, contextToken }) {
            Shopware.Service('salesChannelContextService').updateCustomerContext(customerId, salesChannelId, contextToken);
        },

        updateOrderContext(_, { context, salesChannelId, contextToken }) {
            Shopware.Service('salesChannelContextService').updateContext(context, salesChannelId, contextToken);
        },

        saveOrder(_, { salesChannelId, contextToken }) {
            Shopware.Service('checkOutSalesChannelService').checkout(salesChannelId, contextToken);
        },

        cancelOrder() {
            // TODO: Handle order data
            setTimeout(() => true, 1000);
        }
    }
};
