import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/application
 */
State.register('application', {
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
        applications(state) {
            return state.draft;
        }
    },

    actions: {
        getApplicationList({ commit }, offset = 0, limit = 25) {
            const providerContainer = Shopware.Application.getContainer('service');
            const applicationService = providerContainer.applicationService;

            return applicationService.getList(offset, limit).then((response) => {
                const items = response.data;
                const total = response.meta.total;

                items.forEach((item) => {
                    commit('initApplication', item);
                });

                return {
                    items,
                    total
                };
            });
        }
    },

    mutations: {
        initApplication(state, application) {
            if (!application.id) {
                return;
            }

            const originalApplication = deepCopyObject(application);
            const draftApplication = deepCopyObject(application);

            state.original[application.id] = Object.assign(state.original[application.id] || {}, originalApplication);
            state.draft[application.id] = Object.assign(state.draft[application.id] || {}, draftApplication);
        }
    }
});
