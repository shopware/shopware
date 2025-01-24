/**
 * @sw-package framework
 */
import useSession from '../composables/use-session';

const sessionStore = Shopware.Store.register('session', useSession);

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
