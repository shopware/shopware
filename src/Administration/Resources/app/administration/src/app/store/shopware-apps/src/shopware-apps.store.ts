/**
 * @sw-package framework
 */
import { type AppModuleDefinition, defineStoreOptions, getStore, type StoreOf } from '../dependencies';

/**
 * @public
 */
export interface ShopwareAppsState {
    apps: AppModuleDefinition[];
    appsLoaded: boolean;
    selectedIds: string[];
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const shopwareAppsStoreId = 'shopwareApps';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const shopwareAppsStoreOptions = defineStoreOptions({
    id: shopwareAppsStoreId,

    state: (): {
        apps: AppModuleDefinition[];
        appsLoaded: boolean;
        selectedIds: string[];
    } => ({
        apps: [],
        /**
         * Whether `apps` reflects a finished fetch — an empty list is ambiguous otherwise
         */
        appsLoaded: false,
        selectedIds: [],
    }),
});

/**
 * @public
 */
export type ShopwareApps = StoreOf<typeof shopwareAppsStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useShopwareAppsStore = (): ShopwareApps => getStore(shopwareAppsStoreId);
