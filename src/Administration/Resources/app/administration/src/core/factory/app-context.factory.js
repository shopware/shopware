import useContext from '../../app/composables/use-context';
/**
 * @sw-package framework
 *
 * @private
 * @module core/factory/context
 * @param {Object} context
 * @type factory
 */
export default function createContext(context = {}) {
    // set initial context
    const contextStore = useContext();
    contextStore.app.environment = process.env.NODE_ENV;
    contextStore.app.fallbackLocale = 'en-GB';

    // assign unknown context information
    Object.entries(context).forEach(
        ([
            key,
            value,
        ]) => {
            contextStore.addAppValue({ key, value });
        },
    );

    return Shopware.Context.app;
}
