import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/manufacturer
 */
State.register('manufacturer', {
    namespaced: true,
    strict: true,

    state() {
        return {
            // When entities are loaded, we keep a reference to the original version of the data.
            original: {},
            // For each entity we generate a copy which is the version where all changes are applied.
            draft: {}
        };
    },

    getters: {
        manufacturers(state) {
            return state.draft;
        }
    },

    actions: {
        getManufacturerList({ commit }, offset, limit) {
            const providerContainer = Shopware.Application.getContainer('service');
            const manufacturerService = providerContainer.productManufacturerService;

            return manufacturerService.getList(offset, limit).then((response) => {
                const manufacturers = response.data;
                const total = response.meta.total;

                manufacturers.forEach((manufacturer) => {
                    commit('initManufacturer', manufacturer);
                });

                return {
                    manufacturers,
                    total
                };
            });
        }
    },

    mutations: {
        initManufacturer(state, manufacturer) {
            if (!manufacturer.id) {
                return;
            }

            const originalManufacturer = deepCopyObject(manufacturer);
            const draftManufacturer = deepCopyObject(manufacturer);

            manufacturer.isLoaded = true;
            state.original[manufacturer.id] = Object.assign(state.original[manufacturer.id] || {}, originalManufacturer);
            state.draft[manufacturer.id] = Object.assign(state.draft[manufacturer.id] || {}, draftManufacturer);
        }
    }
});
