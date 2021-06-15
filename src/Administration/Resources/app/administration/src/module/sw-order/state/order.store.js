const { Service } = Shopware;

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
                lineItems: [],
            },
            currency: {
                shortName: 'EUR',
            },
            promotionCodes: [],
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
        },
    },

    getters: {
        isCustomerActive(state) {
            return state?.customer?.active ?? false;
        },

        isCartTokenAvailable(state) {
            return state?.cart?.token ?? null;
        },

        currencyId(state) {
            return state?.currency?.id ?? '';
        },

        invalidPromotionCodes(state) {
            return state.promotionCodes.filter(item => item.isInvalid);
        },

        cartErrors(state) {
            return state?.cart?.errors ?? null;
        },
    },

    actions: {
        selectExistingCustomer({ commit }, { customer }) {
            commit('setCustomer', customer);
            commit('setDefaultSalesChannel', { ...(customer?.salesChannel ?? null) });
        },

        createCart({ commit }, { salesChannelId }) {
            return Service('cartStoreService')
                .createCart(salesChannelId)
                .then(response => {
                    commit('setCartToken', response.data.token);
                });
        },

        getCart({ commit }, { salesChannelId, contextToken }) {
            return Service('cartStoreService')
                .getCart(salesChannelId, contextToken)
                .then((response) => commit('setCart', response.data));
        },

        cancelCart(_, { salesChannelId, contextToken }) {
            return Service('cartStoreService')
                .cancelCart(salesChannelId, contextToken);
        },

        updateCustomerContext(_, { customerId, salesChannelId, contextToken }) {
            return Service('contextStoreService')
                .updateCustomerContext(customerId, salesChannelId, contextToken);
        },

        updateOrderContext(_, { context, salesChannelId, contextToken }) {
            return Service('contextStoreService')
                .updateContext(context, salesChannelId, contextToken);
        },

        saveOrder(_, { salesChannelId, contextToken }) {
            return Service('checkoutStoreService')
                .checkout(salesChannelId, contextToken);
        },

        removeLineItems({ commit }, { salesChannelId, contextToken, lineItemKeys }) {
            return Service('cartStoreService')
                .removeLineItems(salesChannelId, contextToken, lineItemKeys)
                .then(response => commit('setCart', response.data));
        },

        saveLineItem({ commit }, { salesChannelId, contextToken, item }) {
            return Service('cartStoreService')
                .saveLineItem(salesChannelId, contextToken, item)
                .then((response) => commit('setCart', response.data));
        },

        addPromotionCode({ commit }, { salesChannelId, contextToken, code }) {
            return Service('cartStoreService')
                .addPromotionCode(salesChannelId, contextToken, code)
                .then(response => commit('setCart', response.data));
        },

        modifyShippingCosts({ commit }, { salesChannelId, contextToken, shippingCosts }) {
            return Service('cartStoreService')
                .modifyShippingCosts(salesChannelId, contextToken, shippingCosts)
                .then(response => commit('setCart', response.data.data));
        },
    },
};
