/**
 * @sw-package framework
 */
import { getStore, type SetupStoreOf, useContext } from '../dependencies';

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const contextStoreId = 'context';

/**
 * @private
 * The setup function the store is registered with, see `register.ts`.
 */
export const contextStoreSetup = useContext;

/**
 * @public
 */
export type ContextStore = SetupStoreOf<typeof contextStoreId, typeof useContext>;

/**
 * @public
 * The context store holds information about the current context of the application.
 */
export const useContextStore = (): ContextStore => getStore(contextStoreId);
