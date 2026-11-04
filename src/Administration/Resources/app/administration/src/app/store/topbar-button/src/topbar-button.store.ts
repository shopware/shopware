/**
 * @sw-package innovation
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const topBarButtonStoreId = 'topBarButton';

/**
 * @sw-package innovation
 * @private
 * @description Apply for upselling service only, no public usage
 */

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const topBarButtonStoreOptions = defineStoreOptions({
    id: topBarButtonStoreId,

    state: () => ({
        buttons: [] as unknown[],
    }),
});

/**
 * @public
 */
export type TopBarButtonStore = StoreOf<typeof topBarButtonStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useTopBarButtonStore = (): TopBarButtonStore => getStore(topBarButtonStoreId);
