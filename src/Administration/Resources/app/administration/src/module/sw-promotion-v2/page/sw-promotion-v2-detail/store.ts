/**
 * @sw-package checkout
 */
const swPromotionDetailStore = Shopware.Store.register('swPromotionDetail', {
    state() {
        return {
            promotion: null,
            personaCustomerIdsAdd: null,
            personaCustomerIdsDelete: null,
            setGroupIdsDelete: [],
            isLoading: false,
        };
    },
});

/**
 * @private
 */
export default swPromotionDetailStore;

/**
 * @private
 */
export type SwPromotionDetailStore = ReturnType<typeof swPromotionDetailStore>;
