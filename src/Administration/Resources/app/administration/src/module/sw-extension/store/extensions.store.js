import extensionErrorHandler from '../service/extension-error-handler.service';

export default {
    namespaced: true,

    state() {
        return {
            search: {
                page: 1,
                limit: 12,
                rating: null,
                category: null,
                sorting: null,
                term: null
            },
            extensionListing: [],
            storeCategories: [],
            categoriesLanguageId: null,
            licensedExtensions: {
                loading: true,
                data: []
            },
            installedExtensions: {
                loading: true,
                data: []
            },
            shopwareId: null,
            loginStatus: false
        };
    },

    mutations: {
        setSearchValue(state, { key, value }) {
            state.search.page = 1;
            state.search[key] = value;
        },

        setExtensionListing(state, extensions) {
            state.extensionListing = extensions;
        },

        loadInstalledExtensions(state) {
            state.installedExtensions.loading = true;
        },

        installedExtensions(state, installedExtensions) {
            state.installedExtensions.data = installedExtensions;
            state.installedExtensions.loading = false;
        },

        loadLicensedExtensions(state) {
            state.licensedExtensions.loading = true;
        },

        licensedExtensions(state, licensedExtensions) {
            state.licensedExtensions.data = licensedExtensions;
            state.licensedExtensions.loading = false;
        },

        storeCategories(state, categories) {
            state.storeCategories = categories;
        },

        categoriesLanguageId(state, languageId) {
            state.categoriesLanguageId = languageId;
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

        pluginErrorsMapped() { /* nth */ }
    },

    actions: {
        async search({ state, commit }) {
            const extensionDataService = Shopware.Service('extensionStoreDataService');

            const page = await extensionDataService.getExtensionList(
                state.search,
                { ...Shopware.Context.api, languageId: Shopware.State.get('session').languageId }
            );

            commit('setExtensionListing', page);
        },

        async updateInstalledExtensions({ commit }) {
            commit('loadInstalledExtensions');

            const extensionDataService = Shopware.Service('extensionStoreDataService');

            await extensionDataService.refreshExtensions();

            const installedExtensions = await extensionDataService.getInstalledExtensions(
                { ...Shopware.Context.api, languageId: Shopware.State.get('session').languageId }
            );

            commit('installedExtensions', installedExtensions);
        },

        async updateLicensedExtensions({ commit }) {
            commit('loadLicensedExtensions');

            const extensionStoreLicensesService = Shopware.Service('extensionStoreLicensesService');

            let licensedExtensions = [];

            try {
                licensedExtensions = await extensionStoreLicensesService.getLicensedExtensions(
                    { ...Shopware.Context.api, languageId: Shopware.State.get('session').languageId }
                );
            } catch (e) {
                console.log(e);
            }

            commit('licensedExtensions', licensedExtensions);
        },

        async getStoreCategories({ state, commit }) {
            const currentUiLanguageId = Shopware.State.get('session').languageId;

            if (state.storeCategories.length > 0 && state.categoriesLanguageId === currentUiLanguageId) {
                return;
            }
            const extensionStoreCategoryService = Shopware.Service('extensionStoreCategoryService');
            const storeCategories = await extensionStoreCategoryService.getStoreCategories(
                { ...Shopware.Context.api, languageId: currentUiLanguageId }
            );

            commit('storeCategories', storeCategories);
            commit('categoriesLanguageId', currentUiLanguageId);
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

                    const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
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
                    const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
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
        }
    }
};
