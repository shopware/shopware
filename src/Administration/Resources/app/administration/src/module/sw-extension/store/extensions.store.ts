/**
 * @sw-package checkout
 */

import type { UserInfo } from 'src/core/service/api/store.api.service';
import type { Extension } from '../service/extension-store-action.service';
import type { MappedError } from '../service/extension-error-handler.service';

/**
 * @private
 */
export interface ShopwareExtensionsState {
    search: {
        page: number;
        limit: number;
        rating: $TSFixMe;
        sorting: $TSFixMe;
        term: null | string;
        filter: $TSFixMe;
    };
    extensionListing: Extension[];
    categoriesLanguageId: string | null;
    myExtensions: {
        loading: boolean;
        data: Extension[];
    };
    userInfo: UserInfo | null;
}

type SearchValue<T, K extends keyof T> = {
    key: K;
    value: T[K];
};

const shopwareExtensionsStore = Shopware.Store.register({
    id: 'shopwareExtensions',

    state: () =>
        ({
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
        }) as ShopwareExtensionsState,

    actions: {
        setSearchValue<K extends keyof ShopwareExtensionsState['search']>({
            key,
            value,
        }: SearchValue<ShopwareExtensionsState['search'], K>) {
            this.search.page = 1;
            this.search[key] = value;
        },

        loadMyExtensions() {
            this.myExtensions.loading = true;
        },

        setLoading(value: boolean = true) {
            this.myExtensions.loading = value;
        },

        setMyExtensions(myExtensions: Extension[]) {
            this.myExtensions.data = myExtensions;
            this.myExtensions.loading = false;
        },

        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        pluginErrorsMapped(_mappedError: MappedError[]) {
            /* nth */
        },
    },
});

/**
 * @private
 */
export type ShopwareExtensionsStore = ReturnType<typeof shopwareExtensionsStore>;

/**
 * @sw-package checkout
 * @private
 */
export default shopwareExtensionsStore;
