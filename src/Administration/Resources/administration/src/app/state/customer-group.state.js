import { State, Application } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/customerGroup
 */
State.register('customerGroup', {
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
        customerGroups(state) {
            return state.draft;
        }
    },

    actions: {
        getCustomerGroupList({ commit }, offset = 0, limit = 25) {
            const providerContainer = Application.getContainer('service');
            const customerGroupService = providerContainer.customerGroupService;

            return customerGroupService.getList(offset, limit).then((response) => {
                const items = response.data;
                const total = response.meta.total;

                items.forEach((item) => {
                    commit('initCustomerGroup', item);
                });

                return {
                    items,
                    total
                };
            });
        }
    },

    mutations: {
        initCustomerGroup(state, customerGroup) {
            if (!customerGroup.id) {
                return;
            }

            const originalCustomerGroup = deepCopyObject(customerGroup);
            const draftCustomerGroup = deepCopyObject(customerGroup);

            state.original[customerGroup.id] = Object.assign(state.original[customerGroup.id] || {}, originalCustomerGroup);
            state.draft[customerGroup.id] = Object.assign(state.draft[customerGroup.id] || {}, draftCustomerGroup);
        }
    }
});
