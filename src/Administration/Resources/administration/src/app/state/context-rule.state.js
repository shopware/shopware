import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/contextRule
 */
State.register('contextRule', {
    namespaced: true,

    state() {
        return {
            original: {},
            draft: {}
        };
    },

    getters: {
        contextRules(state) {
            return state.draft;
        }
    },

    actions: {
        getContextRuleList({ commit }, { offset = 0, limit = 25 }) {
            const providerContainer = Shopware.Application.getContainer('service');
            const contextRuleService = providerContainer.contextRuleService;

            return contextRuleService.getList(offset, limit).then((response) => {
                const contextRules = response.data;
                const total = response.meta.total;

                contextRules.forEach((contextRule) => {
                    commit('initContextRules', contextRule);
                });

                return {
                    contextRules,
                    total
                };
            });
        }
    },

    mutations: {
        initContextRules(state, contextRule) {
            if (!contextRule.id) {
                return;
            }

            const originalContextRule = deepCopyObject(contextRule);
            const draftContextRule = deepCopyObject(contextRule);

            contextRule.isLoaded = true;
            state.original[contextRule.id] = Object.assign(state.original[contextRule.id] || {}, originalContextRule);
            state.draft[contextRule.id] = Object.assign(state.draft[contextRule.id] || {}, draftContextRule);
        }
    }
});
