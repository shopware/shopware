import { State, Application } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/paymentMethod
 */
State.register('paymentMethod', {
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
        paymentMethods(state) {
            return state.draft;
        }
    },

    actions: {
        getPaymentMethodList({ commit }, offset = 0, limit = 25) {
            const providerContainer = Application.getContainer('service');
            const paymentMethodService = providerContainer.paymentMethodService;

            return paymentMethodService.getList(offset, limit).then((response) => {
                const items = response.data;
                const total = response.meta.total;

                items.forEach((item) => {
                    commit('initPaymentMethod', item);
                });

                return {
                    items,
                    total
                };
            });
        }
    },

    mutations: {
        initPaymentMethod(state, paymentMethod) {
            if (!paymentMethod.id) {
                return;
            }

            const originalPaymentMethod = deepCopyObject(paymentMethod);
            const draftPaymentMethod = deepCopyObject(paymentMethod);

            state.original[paymentMethod.id] = Object.assign(state.original[paymentMethod.id] || {}, originalPaymentMethod);
            state.draft[paymentMethod.id] = Object.assign(state.draft[paymentMethod.id] || {}, draftPaymentMethod);
        }
    }
});
