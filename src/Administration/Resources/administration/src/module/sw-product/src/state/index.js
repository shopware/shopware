import { State } from 'src/core/shopware';
import {
    SET_WORKING,
    RECEIVE_PRODUCT_LIST,
    RECEIVE_PRODUCT_LIST_SUCCESS,
    RECEIVE_PRODUCT_LIST_FAILURE
} from './types';

State.register('productList', {
    state() {
        return {
            isWorking: false,
            products: [],
            lastErrors: {},
            total: 0
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
            state.products = payload.data;
            state.total = payload.total;
            state.isWorking = false;
            state.lastErrors = {};
        },
        [RECEIVE_PRODUCT_LIST_FAILURE](state, payload) {
            state.products = [];
            state.isWorking = false;
            state.lastErrors = payload.data;
        }
    }
});
