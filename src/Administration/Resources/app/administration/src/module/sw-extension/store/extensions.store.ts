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

        categoriesLanguageId(state, languageId: string) {
            state.categoriesLanguageId = languageId;
        },

        setUserInfo(state, userInfo: UserInfo|null) {
            state.userInfo = userInfo;
        },

        pluginErrorsMapped() { /* nth */ },
    },
};

/**
 * @package merchant-services
 * @private
 */
export default shopwareExtensionsStore;

/**
 * @private
 */
export type { ShopwareExtensionsState };
