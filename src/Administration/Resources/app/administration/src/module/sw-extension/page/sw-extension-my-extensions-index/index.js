import template from './sw-extension-my-extensions-index.html.twig';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    inject: ['acl'],

    computed: {
        searchValue: {
            get() {
                return this.$route.query.term || '';
            },

            set(newTerm) {
                this.updateRouteQueryTerm(newTerm);
            },
        },

        queryParams() {
            return {
                term: this.searchValue || undefined,
                limit: this.$route.query.limit,
                page: 1,
            };
        },

        extensionManagementDisabled() {
            return Shopware.Store.get('context').app.config.settings?.disableExtensionManagement;
        },

        tabItems() {
            return [
                this.createTabItem('sw-extension.my-extensions.tabs.app', {
                    name: 'sw.extension.my-extensions.listing.app',
                    query: this.queryParams,
                }),
                this.createTabItem('sw-extension.my-extensions.tabs.theme', {
                    name: 'sw.extension.my-extensions.listing.theme',
                    query: this.queryParams,
                }),
                this.createTabItem('sw-extension.my-extensions.tabs.recommendation', {
                    name: 'sw.extension.my-extensions.recommendation',
                }),
                this.createTabItem('sw-extension.my-extensions.tabs.shopwareAccount', {
                    name: 'sw.extension.my-extensions.account',
                }),
            ];
        },
    },

    methods: {
        createTabItem(label, route) {
            return {
                label: this.$t(label),
                name: route.name,
                onClick: () => this.$router.push(route),
            };
        },

        onSearch(term) {
            this.searchValue = term;
        },

        updateRouteQueryTerm(term) {
            const routeQuery = this.$route.query;

            // Create new route
            const route = {
                name: this.$route.name,
                params: this.$route.params,
                query: {
                    term: term || undefined,
                    limit: this.$route.query.limit,
                    page: 1,
                },
            };

            // If query is empty then replace route, otherwise push
            if (Shopware.Utils.types.isEmpty(routeQuery)) {
                this.$router.replace(route);
            } else {
                this.$router.push(route);
            }
        },
    },
};
