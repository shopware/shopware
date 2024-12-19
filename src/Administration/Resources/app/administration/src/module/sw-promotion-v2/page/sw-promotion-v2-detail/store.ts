/**
 * @package buyers-experience
 * @private
 */
const swPromotionDetailStore = Shopware.Store.register({
    id: 'swPromotionDetail',

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
