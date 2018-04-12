import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/tax
 */
State.register('tax', {
    namespaced: true,

    state() {
        return {
            // When entities are loaded, we keep a reference to the original version of the data.
            original: {},
            // For each entity we generate a copy which is the version where all changes are applied.
            draft: {}
        };
    },

    getters: {
        tax(state) {
            return state.draft;
        }
    },

    actions: {
        getTaxList({ commit }, offset = 0, limit = 25) {
            const providerContainer = Shopware.Application.getContainer('service');
            const taxService = providerContainer.taxService;

            return taxService.getList(offset, limit).then((response) => {
                const taxes = response.data;
                const total = response.meta.total;

                taxes.forEach((tax) => {
                    commit('initTax', tax);
                });

                return {
                    taxes,
                    total
                };
            });
        }
    },

    mutations: {
        initTax(state, tax) {
            if (!tax.id) {
                return;
            }

            const originalTax = deepCopyObject(tax);
            const draftTax = deepCopyObject(tax);

            tax.isLoaded = true;
            state.original[tax.id] = Object.assign(state.original[tax.id] || {}, originalTax);
            state.draft[tax.id] = Object.assign(state.draft[tax.id] || {}, draftTax);
        }
    }
});
