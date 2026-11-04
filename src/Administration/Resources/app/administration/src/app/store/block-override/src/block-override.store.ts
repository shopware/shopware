/**
 * @sw-package framework
 */
import { getStore, type SetupStoreOf, useBlockContext } from '../dependencies';

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const blockOverrideStoreId = 'blockOverride';

/**
 * @private
 * The setup function the store is registered with, see `register.ts`.
 */
export const blockOverrideStoreSetup = useBlockContext;

/**
 * @public
 */
export type BlockOverrideStore = SetupStoreOf<typeof blockOverrideStoreId, typeof useBlockContext>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useBlockOverrideStore = (): BlockOverrideStore => getStore(blockOverrideStoreId);
