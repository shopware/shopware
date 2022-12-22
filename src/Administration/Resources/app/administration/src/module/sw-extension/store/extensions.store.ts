import type { Module } from 'vuex';
import type { UserInfo } from 'src/core/service/api/store.api.service';
import type { Extension } from '../service/extension-store-action.service';

interface ShopwareExtensionsState {
    search: {
        page: number,
        limit: number,
        rating: $TSFixMe,
        sorting: $TSFixMe,
        term: null|string,
        filter: $TSFixMe,
    }
    extensionListing: Extension[],
    categoriesLanguageId: string|null,
    myExtensions: {
        loading: boolean,
        data: Extension[]
    }
    userInfo: UserInfo|null,
    // @deprecated tag:v6.5.0 - will be removed. Check existence of userInfo instead
    shopwareId: string|null,
    // @deprecated tag:v6.5.0 - will be removed. Check existence of userInfo instead
    loginStatus: boolean
    // @deprecated tag:v6.5.0 - will be removed
    licensedExtensions: {
        loading: boolean,
        data: $TSFixMe
    }
    // @deprecated tag:v6.5.0 - will be removed
    plugins: $TSFixMe,
    // @deprecated tag:v6.5.0 - will be removed
    totalPlugins: number,
}

type SearchValue<T, K extends keyof T> = {
    key: K,
    value: T[K]
}

const shopwareExtensionsStore: Module<ShopwareExtensionsState, VuexRootState> = {
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
            userInfo: null,
            shopwareId: null,
            loginStatus: false,
            licensedExtensions: {
                loading: false,
                data: [],
            },
            totalPlugins: 0,
            plugins: null,
        };
    },

    mutations: {
        setSearchValue<K extends keyof ShopwareExtensionsState['search']>(
            state: ShopwareExtensionsState,
            { key, value }: SearchValue<ShopwareExtensionsState['search'], K>,
        ) {
            state.search.page = 1;
            state.search[key] = value;
        },

        setExtensionListing(state, extensions: Extension[]) {
            state.extensionListing = extensions;
        },

        loadMyExtensions(state) {
            state.myExtensions.loading = true;
        },

        // eslint-disable-next-line @typescript-eslint/no-inferrable-types
        setLoading(state, value: boolean = true) {
            state.myExtensions.loading = value;
        },

        myExtensions(state, myExtensions: Extension[]) {
            state.myExtensions.data = myExtensions;
            state.myExtensions.loading = false;
        },

        /**
         * @deprecated tag:v6.5.0 - will be removed
         */
        loadLicensedExtensions(state) {
            state.licensedExtensions.loading = true;
        },

        /**
         * @deprecated tag:v6.5.0 - will be removed
         */
        licensedExtensions(state, licensedExtensions) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            state.licensedExtensions.data = licensedExtensions;
            state.licensedExtensions.loading = false;
        },

        categoriesLanguageId(state, languageId: string) {
            state.categoriesLanguageId = languageId;
        },

        setUserInfo(state, userInfo: UserInfo|null) {
            state.userInfo = userInfo;
        },

        /**
         * @deprecated tag:v6.5.0 - will be removed
         */
        storeShopwareId(state, shopwareId: string|null) {
            state.shopwareId = shopwareId;
        },

        /**
         * @deprecated tag:v6.5.0 - will be removed
         */
        setLoginStatus(state, loginStatus: boolean) {
            state.loginStatus = loginStatus;
        },

        /**
         * @deprecated tag:v6.5.0 - will be removed
         */
        commitPlugins(state, searchResult) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            state.plugins = searchResult;
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
            state.totalPlugins = searchResult.total;
        },

        pluginErrorsMapped() { /* nth */ },
    },
};

/**
 * @package merchant-services
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default shopwareExtensionsStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ShopwareExtensionsState };
