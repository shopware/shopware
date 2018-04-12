import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/currency
 */
State.register('currency', {
    namespaced: true,

    state() {
        return {
            original: {},
            draft: {}
        };
    },

    getters: {
        currencies(state) {
            return state.draft;
        }
    },

    actions: {
        getCurrencyList({ commit }, offset = 0, limit = 25) {
            const providerContainer = Shopware.Application.getContainer('service');
            const currencyService = providerContainer.currencyService;

            return currencyService.getList(offset, limit).then((response) => {
                const currencies = response.data;
                const total = response.meta.total;

                currencies.forEach((currency) => {
                    commit('initCurrency', currency);
                });

                return {
                    currencies,
                    total
                };
            });
        }
    },

    mutations: {
        initCurrency(state, currency) {
            if (!currency.id) {
                return;
            }

            const originalCurrency = deepCopyObject(currency);
            const draftCurrency = deepCopyObject(currency);

            state.original[currency.id] = Object.assign(state.original[currency.id] || {}, originalCurrency);
            state.draft[currency.id] = Object.assign(state.draft[currency.id] || {}, draftCurrency);
        }
    }
});
