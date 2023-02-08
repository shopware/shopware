/**
 * @package admin
 *
 * @module core/factory/context
 * @param {Object} context
 * @type factory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createContext(context = {}) {
    // set initial context
    Shopware.State.commit('context/setAppEnvironment', process.env.NODE_ENV);
    Shopware.State.commit('context/setAppFallbackLocale', 'en-GB');

    // assign unknown context information
    Object.entries(context).forEach(([key, value]) => {
        Shopware.State.commit('context/addAppValue', { key, value });
    });

    // set initial last activity to prevent immediate logout
    Shopware.State.commit('context/addAppValue', {
        key: 'lastActivity',
        value: Math.round(+new Date() / 1000),
    });

    return Shopware.Context.app;
}
