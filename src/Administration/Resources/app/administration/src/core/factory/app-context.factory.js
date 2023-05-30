/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @module core/factory/context
 * @param {Object} context
 * @type factory
 */
export default function createContext(context = {}) {
    // set initial context
    Shopware.State.commit('context/setAppEnvironment', process.env.NODE_ENV);
    Shopware.State.commit('context/setAppFallbackLocale', 'en-GB');

    // assign unknown context information
    Object.entries(context).forEach(([key, value]) => {
        Shopware.State.commit('context/addAppValue', { key, value });
    });

    return Shopware.Context.app;
}
