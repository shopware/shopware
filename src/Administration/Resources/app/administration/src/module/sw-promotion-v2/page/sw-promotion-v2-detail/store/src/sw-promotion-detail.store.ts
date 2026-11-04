/**
 * @sw-package checkout
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const swPromotionDetailStoreId = 'swPromotionDetail';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const swPromotionDetailStoreOptions = defineStoreOptions({
    id: swPromotionDetailStoreId,

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
 * @public
 */
export type SwPromotionDetailStore = StoreOf<typeof swPromotionDetailStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so the page, its views and extensions share one state.
 */
export const useSwPromotionDetailStore = (): SwPromotionDetailStore => getStore(swPromotionDetailStoreId);
