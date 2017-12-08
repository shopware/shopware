import {
    SET_WORKING,
    RECEIVE_PRODUCT_LIST,
    RECEIVE_PRODUCT_LIST_SUCCESS,
    RECEIVE_PRODUCT_LIST_FAILURE
} from './types';

Shopware.State.register('productList', {
    state() {
        return {
            isWorking: false,
            productList: [],
            lastErrors: {}
        };
    },
    actions: {
        async [RECEIVE_PRODUCT_LIST]({ commit }, opts) {
            const providerContainer = Shopware.Application.getContainer('service');
            const productService = providerContainer.productService;

            commit(SET_WORKING, true);

            productService.getList(opts.offset, opts.limit)
                .then((response) => commit(RECEIVE_PRODUCT_LIST_SUCCESS, response))
                .catch((response) => commit(RECEIVE_PRODUCT_LIST_FAILURE, response));
        }
    },

    mutations: {
        [SET_WORKING](state, payload) {
            state.isWorking = payload;
        },
        [RECEIVE_PRODUCT_LIST_SUCCESS](state, payload) {
            state.productList = payload.data;
            state.isWorking = false;
            state.lastErrors = {};
        },
        [RECEIVE_PRODUCT_LIST_FAILURE](state, payload) {
            state.productList = [];
            state.isWorking = false;
            state.lastErrors = payload.data;
        }
    }
});
