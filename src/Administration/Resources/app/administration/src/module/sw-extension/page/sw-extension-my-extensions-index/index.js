import template from './sw-extension-my-extensions-index.html.twig';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    inject: ['acl'],

    computed: {
        Shopware() {
            return Shopware;
        },

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

        tabItems() {
            return [
                {
                    label: this.$t('sw-extension.my-extensions.tabs.app'),
                    name: 'app',
                    route: { name: 'sw.extension.my-extensions.listing.app', query: this.queryParams },
                    onClick: () => {
                        this.$router.push({ name: 'sw.extension.my-extensions.listing.app', query: this.queryParams });
                    },
                },
                {
                    label: this.$t('sw-extension.my-extensions.tabs.theme'),
                    name: 'theme',
                    route: { name: 'sw.extension.my-extensions.listing.theme', query: this.queryParams },
                    onClick: () => {
                        this.$router.push({ name: 'sw.extension.my-extensions.listing.theme', query: this.queryParams });
                    },
                },
                {
                    label: this.$t('sw-extension.my-extensions.tabs.recommendation'),
                    name: 'recommendation',
                    route: { name: 'sw.extension.my-extensions.recommendation' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.extension.my-extensions.recommendation' });
                    },
                },
                {
                    label: this.$t('sw-extension.my-extensions.tabs.shopwareAccount'),
                    name: 'account',
                    route: { name: 'sw.extension.my-extensions.account' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.extension.my-extensions.account' });
                    },
                },
            ];
        },

        defaultTabItem() {
            const routeMap = {
                'sw.extension.my-extensions.listing.app': 'app',
                'sw.extension.my-extensions.listing.theme': 'theme',
                'sw.extension.my-extensions.recommendation': 'recommendation',
                'sw.extension.my-extensions.account': 'account',
            };

            return routeMap[this.$route.name] ?? 'app';
        },

        extensionManagementDisabled() {
            return Shopware.Store.get('context').app.config.settings?.disableExtensionManagement;
        },
    },

    methods: {
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
