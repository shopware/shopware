/**
 * @package admin
 * @private
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */
export default {
    namespaced: true,
    state: {
        routes: {},
    },

    mutations: {
        addItem(state, config) {
            Shopware.Application.view.setReactive(state.routes, config.extensionName, config);
        },
    },
};
