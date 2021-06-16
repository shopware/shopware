export default {
    namespaced: true,

    state() {
        return {
            search: {
                page: 1,
                limit: 12,
                rating: null,
                sorting: null,
                term: null,
                filter: {},
            },
            extensionListing: [],
            categoriesLanguageId: null,
            myExtensions: {
                loading: true,
                data: [],
            },
            shopwareId: null,
            loginStatus: false,
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

        loadMyExtensions(state) {
            state.myExtensions.loading = true;
        },

        setLoading(state, value = true) {
            state.myExtensions.loading = value;
        },

        myExtensions(state, myExtensions) {
            state.myExtensions.data = myExtensions;
            state.myExtensions.loading = false;
        },

        loadLicensedExtensions(state) {
            state.licensedExtensions.loading = true;
        },

        licensedExtensions(state, licensedExtensions) {
            state.licensedExtensions.data = licensedExtensions;
            state.licensedExtensions.loading = false;
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

        pluginErrorsMapped() { /* nth */ },
    },
};
