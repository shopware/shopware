const { Utils, Service } = Shopware;
const { get } = Utils;

function filterEmptyLineItems(items) {
    return items.filter(item => item.label === '');
}

function reverseLineItems(items) {
    return items.slice().reverse();
}

function mergeEmptyAndExistingLineItems(emptyLineItems, lineItems) {
    // Reverse the lineItems so the newly added are at the top for better UX
    reverseLineItems(lineItems);

    return [...emptyLineItems, ...lineItems];
}

export default {
    namespaced: true,

    state() {
        return {
            customer: null,
            defaultSalesChannel: null,
            cart: {
                token: null,
                lineItems: []
            },
            currency: {
                shortName: 'EUR'
            },
            promotionCodes: []
        };
    },

    mutations: {
        setCustomer(state, customer) {
            state.customer = customer;
        },

        setDefaultSalesChannel(state, salesChannel) {
            state.defaultSalesChannel = salesChannel;
        },

        setCartToken(state, token) {
            state.cart.token = token;
        },

        setCart(state, cart) {
            const emptyLineItems = filterEmptyLineItems(state.cart.lineItems);
            state.cart = cart;
            state.cart.lineItems = mergeEmptyAndExistingLineItems(emptyLineItems, state.cart.lineItems);
        },

        setCartLineItems(state, lineItems) {
            state.cart.lineItems = lineItems;
        },

        setCurrency(state, currency) {
            state.currency = currency;
        },

        setPromotionCodes(state, promotionCodes) {
            state.promotionCodes = promotionCodes;
        },

        removeEmptyLineItem(state, emptyLineItemKey) {
            state.cart.lineItems = state.cart.lineItems.filter(item => item.id !== emptyLineItemKey);
        },

        removeInvalidPromotionCodes(state) {
            state.promotionCodes = state.promotionCodes.filter(item => !item.isInvalid);
        }
    },

    getters: {
        isCustomerActive(state) {
            return get(state, 'customer.active', false);
        },

        isCartTokenAvailable(state) {
            return get(state, 'cart.token', null);
        },

        currencyId(state) {
            return get(state, 'currency.id', '');
        },

        invalidPromotionCodes(state) {
            return state.promotionCodes.filter(item => item.isInvalid);
        }
    },

    actions: {
        selectExistingCustomer({ commit }, { customer }) {
            commit('setCustomer', customer);
            commit('setDefaultSalesChannel', { ...get(customer, 'salesChannel', null) });
        },

        createCart({ commit }, { salesChannelId }) {
            return Service('cartSalesChannelService')
                .createCart(salesChannelId)
                .then(response => {
                    commit('setCartToken', response.data['sw-context-token']);
                });
        },

        getCart({ commit }, { salesChannelId, contextToken }) {
            return Service('cartSalesChannelService')
                .getCart(salesChannelId, contextToken)
                .then((response) => commit('setCart', response.data.data));
        },

        cancelCart(_, { salesChannelId, contextToken }) {
            return Service('cartSalesChannelService')
                .cancelCart(salesChannelId, contextToken);
        },

        updateCustomerContext(_, { customerId, salesChannelId, contextToken }) {
            return Service('salesChannelContextService')
                .updateCustomerContext(customerId, salesChannelId, contextToken);
        },

        updateOrderContext(_, { context, salesChannelId, contextToken }) {
            return Service('salesChannelContextService')
                .updateContext(context, salesChannelId, contextToken);
        },

        saveOrder(_, { salesChannelId, contextToken }) {
            return Service('checkOutSalesChannelService')
                .checkout(salesChannelId, contextToken);
        },

        removeLineItems({ commit }, { salesChannelId, contextToken, lineItemKeys }) {
            return Service('cartSalesChannelService')
                .removeLineItems(salesChannelId, contextToken, lineItemKeys)
                .then(response => commit('setCart', response.data.data));
        },

        saveLineItem({ commit }, { salesChannelId, contextToken, item }) {
            return Service('cartSalesChannelService')
                .saveLineItem(salesChannelId, contextToken, item)
                .then((response) => commit('setCart', response.data.data));
        },

        addPromotionCode({ commit }, { salesChannelId, contextToken, code }) {
            return Service('cartSalesChannelService')
                .addPromotionCode(salesChannelId, contextToken, code)
                .then(response => commit('setCart', response.data.data));
        },

        modifyShippingCosts({ commit }, { salesChannelId, contextToken, shippingCosts }) {
            return Service('cartSalesChannelService')
                .modifyShippingCosts(salesChannelId, contextToken, shippingCosts)
                .then(response => commit('setCart', response.data.data));
        }
    }
};
