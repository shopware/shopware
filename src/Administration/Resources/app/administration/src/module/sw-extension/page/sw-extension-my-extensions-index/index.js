import template from './sw-extension-my-extensions-index.html.twig';

/**
 * @package merchant-services
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
