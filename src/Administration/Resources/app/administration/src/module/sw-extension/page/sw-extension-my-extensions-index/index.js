import template from './sw-extension-my-extensions-index.html.twig';

/**
 * @private
 */
Shopware.Component.register('sw-extension-my-extensions-index', {
    template,

    computed: {
        searchValue: {
            get() {
                return this.$route.query.term || '';
            },

            set(newTerm) {
                this.updateRouteQueryTerm(newTerm);
            },
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
});
