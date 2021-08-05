/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
export default {
    namespaced: true,

    state() {
        return {
            promotion: null,
            personaCustomerIdsAdd: null,
            personaCustomerIdsDelete: null,
            setGroupIdsDelete: [],
            isLoading: false,
        };
    },

    mutations: {
        setPromotion(state, promotion) {
            state.promotion = promotion;
        },

        setPersonaCustomerIdsAdd(state, customerIds) {
            state.personaCustomerIdsAdd = customerIds;
        },

        setPersonaCustomerIdsDelete(state, customerIds) {
            state.personaCustomerIdsDelete = customerIds;
        },

        setSetGroupIdsDelete(state, groupIds) {
            state.setGroupIdsDelete = groupIds;
        },

        setIsLoading(state, isLoading) {
            state.isLoading = isLoading;
        },
    },
};
