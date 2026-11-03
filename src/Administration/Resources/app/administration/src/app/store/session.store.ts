/**
 * @sw-package framework
 */
import useSession from '../composables/use-session';

/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const sessionStoreId = 'session';

const sessionStore = Shopware.Store.register(sessionStoreId, useSession);

/**
 * @private
 * @description
 * The context store holds information about the current context of the application.
 */
export default sessionStore;

/**
 * @private
 */
export type SessionStore = ReturnType<typeof sessionStore>;
