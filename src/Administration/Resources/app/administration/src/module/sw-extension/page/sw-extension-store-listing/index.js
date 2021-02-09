import template from './sw-extension-store-listing.html.twig';
import './sw-extension-store-listing.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-store-listing', {
    name: 'sw-extension-store-listing',
    template,

    inject: ['feature'],

    mixins: ['sw-extension-error'],

    data() {
        return {
            isLoading: false
        };
    },

    computed: {
        extensions() {
            return Shopware.State.get('shopwareExtensions').extensionListing;
        },

        currentSearch() {
            return Shopware.State.get('shopwareExtensions').search;
        },

        page() {
            return this.currentSearch.page;
        },

        limit() {
            return this.currentSearch.limit;
        },

        total() {
            return this.extensions.total || 0;
        },

        rating() {
            return this.currentSearch.rating;
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        currentLocale() {
            return Shopware.State.get('session').currentLocale === 'de-DE' ? 'de' : 'en';
        }
    },

    watch: {
        currentSearch: {
            deep: true,
            immediate: true,
            handler() {
                this.getList();
            }
        },
        languageId(newValue) {
            if (newValue !== '') {
                this.getList();
            }
        }
    },

    methods: {
        async getList() {
            this.isLoading = true;

            if (this.languageId === '') {
                return;
            }

            try {
                await this.search();
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async search() {
            const extensionDataService = Shopware.Service('extensionStoreDataService');

            const page = await extensionDataService.getExtensionList(
                Shopware.State.get('shopwareExtensions').search,
                { ...Shopware.Context.api, languageId: Shopware.State.get('session').languageId }
            );

            Shopware.State.commit('shopwareExtensions/setExtensionListing', page);
        },

        setPage({ limit, page }) {
            Shopware.State.commit('shopwareExtensions/setSearchValue', { key: 'limit', value: limit });
            Shopware.State.commit('shopwareExtensions/setSearchValue', { key: 'page', value: page });
        }
    }
});
