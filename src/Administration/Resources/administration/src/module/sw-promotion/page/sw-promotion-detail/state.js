export default {
    namespaced: true,

    state() {
        return {
            promotion: null,
            discounts: null,
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

        setIsLoading(state, isLoading) {
            state.isLoading = isLoading;
        }
    }
};
