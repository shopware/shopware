/**
 * @package buyers-experience
 * @private
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
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
