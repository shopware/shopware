import pluginErrorHandler from '../service/plugin-error-handler.service';

export default {
    namespaced: true,
    state: {
        shopwareId: null,
        loginStatus: false,
        storeAvailable: false,
        availableUpdates: null,
        updates: [],
        plugins: null,
        totalPlugins: 0
    },

    mutations: {
        storeAvailable(state, isAvailable) {
            state.storeAvailable = isAvailable;
        },

        storeShopwareId(state, shopwareId) {
            state.shopwareId = shopwareId;
        },

        setLoginStatus(state, loginStatus) {
            state.loginStatus = loginStatus;
        },

        commitPlugins(state, searchResult) {
            state.plugins = searchResult;
            state.totalPlugins = searchResult.total;
        },

        availableUpdates(state, { items = [], total = 0 } = {}) {
            state.updates = items;
            state.availableUpdates = total;
        },

        pluginErrorsMapped() { /* nth */ }
    },

    actions: {
        pingStore({ commit }) {
            return Shopware.Service('storeService').ping().then(() => {
                commit('storeAvailable', true);
                return true;
            }).catch((errorResponse) => {
                const mappedErrors = pluginErrorHandler.mapErrors(errorResponse.response.data.errors);
                commit('pluginErrorsMapped', mappedErrors);
                commit('storeAvailable', false);

                throw errorResponse;
            });
        },

        loginShopwareUser({ commit, dispatch }, { shopwareId, password }) {
            return Shopware.Service('storeService').login(shopwareId, password)
                .then(() => {
                    commit('storeShopwareId', shopwareId);
                    return dispatch('checkLogin');
                })
                .catch((errorResponse) => {
                    commit('storeShopwareId', null);
                    commit('setLoginStatus', false);

                    const mappedErrors = pluginErrorHandler.mapErrors(errorResponse.response.data.errors);
                    commit('pluginErrorsMapped', mappedErrors);

                    throw errorResponse;
                });
        },

        storeShopwareId({ commit }, shopwareId) {
            commit('storeShopwareId', shopwareId);
        },

        logoutShopwareUser({ commit }) {
            return Shopware.Service('storeService').logout()
                .then(() => {
                    commit('storeShopwareId', null);
                    commit('setLoginStatus', false);
                })
                .catch((errorResponse) => {
                    const mappedErrors = pluginErrorHandler.mapErrors(errorResponse.response.data.errors);
                    commit('pluginErrorsMapped', mappedErrors);

                    throw errorResponse;
                });
        },

        checkLogin({ state, commit }) {
            if (!state.shopwareId) {
                commit('setLoginStatus', false);
            }

            Shopware.Service('storeService').checkLogin().then((response) => {
                commit('setLoginStatus', response.storeTokenExists);
            }).catch(() => {
                commit('setLoginStatus', false);
            });
        },

        fetchAvailableUpdates({ commit }) {
            return Shopware.Service('storeService').getUpdateList().then((response) => {
                commit('availableUpdates', response);
            }).catch((errorResponse) => {
                const mappedErrors = pluginErrorHandler.mapErrors(errorResponse.response.data.errors);
                commit('pluginErrorsMapped', mappedErrors);

                throw errorResponse;
            });
        },

        updatePluginList({ commit }, { repository, criteria, context }) {
            return Shopware.Service('pluginService').refresh()
                .then(() => {
                    return repository.search(criteria, context);
                }).then((searchResult) => {
                    commit('commitPlugins', searchResult);
                });
        }
    }
};
