/**
 * @sw-package framework
 */
import useContext from '../composables/use-context';

const contextStore = Shopware.Store.register('context', useContext);

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
