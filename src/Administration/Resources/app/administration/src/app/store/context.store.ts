/**
 * @sw-package framework
 */
import useContext from '../composables/use-context';

/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const contextStoreId = 'context';

const contextStore = Shopware.Store.register(contextStoreId, useContext);

/**
 * @private
 * @description
 * The context store holds information about the current context of the application.
 */
export default contextStore;

/**
 * @private
 */
export type ContextStore = ReturnType<typeof contextStore>;
