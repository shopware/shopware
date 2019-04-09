import { Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import { debug } from 'src/core/service/util.service';
import types from 'src/core/service/utils/types.utils';

Mixin.register('listing', {

    data() {
        return {
            page: 1,
            limit: 25,
            total: 0,
            sortBy: null,
            sortDirection: 'ASC',
            term: undefined,
            disableRouteParams: false
        };
    },

    computed: {
        maxPage() {
            return Math.ceil(this.total / this.limit);
        },

        routeName() {
            return this.$route.name;
        },

        filters() {
            debug.warn('Listing Mixin', 'You can create your custom filters by defining the computed property "filters".');
            return [];
        }
    },

    created() {
        if (this.disableRouteParams) {
            this.getList();
            return;
        }
        const actualQueryParameters = this.$route.query;

        // When no route information are provided
        if (types.isEmpty(actualQueryParameters)) {
            this.resetListing();
            this.updateRoute();
        } else {
            // otherwise update local data and fetch from server
            this.updateData(actualQueryParameters);
            this.getList();
        }
    },

    watch: {
        // Watch for changes in query parameters and update listing
        '$route'() {
            if (this.disableRouteParams) {
                return;
            }
            const query = this.$route.query;

            if (types.isEmpty(query)) {
                this.resetListing();
            }

            // Update data information from the url
            this.updateData(query);

            // Fetch new list
            this.getList();
        }
    },

    methods: {
        updateData(customData) {
            this.page = parseInt(customData.page, 10) || this.page;
            this.limit = parseInt(customData.limit, 10) || this.limit;
            this.term = customData.term || this.term;
            this.sortBy = customData.sortBy || this.sortBy;
            this.sortDirection = customData.sortDirection || this.sortDirection;
        },

        updateRoute(customQuery, queryExtension = {}) {
            // Get actual query parameter
            const query = customQuery || this.$route.query;
            const routeQuery = this.$route.query;

            // Create new route
            const route = {
                name: this.$route.name,
                query: {
                    limit: query.limit || this.limit,
                    page: query.page || this.page,
                    term: query.term || this.term,
                    sortBy: query.sortBy || this.sortBy,
                    sortDirection: query.sortDirection || this.sortDirection,
                    ...queryExtension
                }
            };

            // If query is empty then replace route, otherwise push
            if (types.isEmpty(routeQuery)) {
                this.$router.replace(route);
            } else {
                this.$router.push(route);
            }
        },

        resetListing() {
            this.updateRoute({
                name: this.$route.name,
                query: {
                    limit: this.limit,
                    page: this.page,
                    term: this.term,
                    sortBy: this.sortBy,
                    sortDirection: this.sortDirection
                }
            });
        },

        getListingParams() {
            if (this.disableRouteParams) {
                return {
                    limit: this.limit,
                    page: this.page,
                    term: this.term,
                    sortBy: this.sortBy,
                    sortDirection: this.sortDirection
                };
            }
            // Get actual query parameter
            const query = this.$route.query;

            const params = {
                limit: query.limit,
                page: query.page,
                term: query.term,
                sortBy: query.sortBy || this.sortBy,
                sortDirection: query.sortDirection || this.sortDirection
            };

            const criteria = this.generateCriteriaFromFilters(this.filters);

            if (criteria) {
                params.criteria = criteria;
            }

            return params;
        },

        generateCriteriaFromFilters(filters, operator = 'AND') {
            const terms = [];

            this.filters.forEach((filter) => {
                if (!filter.active) {
                    return;
                }

                const criteria = filter.criteria;
                const term = CriteriaFactory[criteria.type](criteria.field, criteria.options);

                terms.push(term);
            });

            if (!terms.length) {
                return null;
            }

            return CriteriaFactory.multi(operator, ...terms);
        },

        onPageChange(opts) {
            this.page = opts.page;
            this.limit = opts.limit;
            if (this.disableRouteParams) {
                this.getList();
                return;
            }
            this.updateRoute({
                page: this.page
            });
        },

        onSearch(value) {
            if (value.length === 0) value = undefined;

            if (this.disableRouteParams) {
                this.term = value;
                this.page = 1;
                this.getList();
            }

            this.term = value;
            this.updateRoute({
                term: this.term,
                page: 1
            });
        },

        onSwitchFilter(filter, filterIndex) {
            this.filters[filterIndex].active = !this.filters[filterIndex].active;

            this.page = 1;
        },

        onSortColumn(column) {
            if (this.disableRouteParams) {
                if (this.sortBy === column.dataIndex) {
                    this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
                } else {
                    this.sortDirection = 'ASC';
                    this.sortBy = column.dataIndex;
                }
                this.getList();
                return;
            }

            if (this.sortBy === column.dataIndex) {
                this.updateRoute({
                    sortDirection: (this.sortDirection === 'ASC' ? 'DESC' : 'ASC')
                });
            } else {
                this.updateRoute({
                    sortBy: column.dataIndex,
                    sortDirection: 'ASC'
                });
            }
            this.updateRoute();
        },

        onRefresh() {
            this.getList();
        },

        getList() {
            debug.warn(
                'Listing Mixin',
                'When using the listing mixin you have to implement your custom "getList()" method.'
            );
        }
    }
});
