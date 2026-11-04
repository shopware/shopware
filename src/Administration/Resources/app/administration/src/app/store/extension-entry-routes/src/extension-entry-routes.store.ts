/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
interface EntryRouteConfig {
    extensionName: string;
    route: string;
    label?: string;
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const extensionEntryRoutesStoreId = 'extensionEntryRoutes';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const extensionEntryRoutesStoreOptions = defineStoreOptions({
    id: extensionEntryRoutesStoreId,

    state: () => ({
        routes: {} as Record<string, EntryRouteConfig>,
    }),

    actions: {
        addItem(config: EntryRouteConfig) {
            this.routes[config.extensionName] = config;
        },
    },
});

/**
 * @public
 */
export type ExtensionEntryRoutes = StoreOf<typeof extensionEntryRoutesStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useExtensionEntryRoutesStore = (): ExtensionEntryRoutes => getStore(extensionEntryRoutesStoreId);
