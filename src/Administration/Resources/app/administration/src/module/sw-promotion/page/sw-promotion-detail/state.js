export default {
    namespaced: true,

    state() {
        return {
            promotion: null,
            discounts: null,
            personaCustomerIdsAdd: null,
            personaCustomerIdsDelete: null,
            setGroupIdsDelete: [],
            isLoading: false
        };
    },

    mutations: {
        setPromotion(state, promotion) {
            state.promotion = promotion;
        },

        setDiscounts(state, discounts) {
            state.discounts = discounts;
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
        }
    }
};
