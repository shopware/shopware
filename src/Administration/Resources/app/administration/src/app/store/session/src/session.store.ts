/**
 * @sw-package framework
 */
import { getStore, type SetupStoreOf, useSession } from '../dependencies';

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const sessionStoreId = 'session';

/**
 * @private
 * The setup function the store is registered with, see `register.ts`.
 */
export const sessionStoreSetup = useSession;

/**
 * @public
 */
export type SessionStore = SetupStoreOf<typeof sessionStoreId, typeof useSession>;

/**
 * @public
 * The context store holds information about the current context of the application.
 */
export const useSessionStore = (): SessionStore => getStore(sessionStoreId);
