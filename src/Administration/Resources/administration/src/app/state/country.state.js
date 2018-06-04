import { State, Application } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/country
 */
State.register('country', {
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
        countries(state) {
            return state.draft;
        }
    },

    actions: {
        getCountryList({ commit }, offset = 0, limit = 25) {
            const providerContainer = Application.getContainer('service');
            const countryService = providerContainer.countryService;

            return countryService.getList(offset, limit).then((response) => {
                const items = response.data;
                const total = response.meta.total;

                items.forEach((item) => {
                    commit('initCountry', item);
                });

                return {
                    items,
                    total
                };
            });
        }
    },

    mutations: {
        initCountry(state, country) {
            if (!country.id) {
                return;
            }

            const originalCountry = deepCopyObject(country);
            const draftCountry = deepCopyObject(country);

            state.original[country.id] = Object.assign(state.original[country.id] || {}, originalCountry);
            state.draft[country.id] = Object.assign(state.draft[country.id] || {}, draftCountry);
        }
    }
});
