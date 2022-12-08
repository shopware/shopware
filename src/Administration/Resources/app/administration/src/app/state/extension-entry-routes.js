/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
