/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const swPromotionDetailStoreId = 'swPromotionDetail';

/**
 * @sw-package checkout
 */
const swPromotionDetailStore = Shopware.Store.register(swPromotionDetailStoreId, {
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
