/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const adminHelpCenterStoreId = 'adminHelpCenter';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const adminHelpCenterStoreOptions = defineStoreOptions({
    id: adminHelpCenterStoreId,

    state: () => {
        return {
            showHelpSidebar: false,
            showShortcutModal: false,
        };
    },
});

/**
 * @public
 */
export type AdminHelpCenterStore = StoreOf<typeof adminHelpCenterStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useAdminHelpCenterStore = (): AdminHelpCenterStore => getStore(adminHelpCenterStoreId);
