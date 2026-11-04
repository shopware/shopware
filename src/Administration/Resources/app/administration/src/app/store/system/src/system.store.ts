/**
 * @sw-package framework
 */
import { getStore, type SetupStoreOf, useSystem } from '../dependencies';

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const systemStoreId = 'system';

/**
 * @private
 * The setup function the store is registered with, see `register.ts`.
 */
export const systemStoreSetup = useSystem;

/**
 * @public
 */
export type System = SetupStoreOf<typeof systemStoreId, typeof useSystem>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useSystemStore = (): System => getStore(systemStoreId);
