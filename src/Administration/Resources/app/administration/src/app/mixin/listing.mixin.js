const { Mixin } = Shopware;
// @deprecated tag:v6.4.0.0
const { CriteriaFactory } = Shopware.DataDeprecated;
const types = Shopware.Utils.types;
const { debug } = Shopware.Utils;

Mixin.register('listing', {
    data() {
        return {
            page: 1,
            limit: 25,
            total: 0,
            sortBy: null,
            sortDirection: 'ASC',
            naturalSorting: false,
            selection: [],
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

        selectionArray() {
            return Object.values(this.selection);
        },

        selectionCount() {
            return this.selectionArray.length;
        },

        filters() {
            // You can create your custom filters by defining the computed property "filters"
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
            this.naturalSorting = customData.naturalSorting || this.naturalSorting;
        },

        updateRoute(customQuery, queryExtension = {}) {
            // Get actual query parameter
            const query = customQuery || this.$route.query;
            const routeQuery = this.$route.query;

            // Create new route
            const route = {
                name: this.$route.name,
                params: this.$route.params,
                query: {
                    limit: query.limit || this.limit,
                    page: query.page || this.page,
                    term: query.term || this.term,
                    sortBy: query.sortBy || this.sortBy,
                    sortDirection: query.sortDirection || this.sortDirection,
                    naturalSorting: query.naturalSorting || this.naturalSorting,
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
                    sortDirection: this.sortDirection,
                    naturalSorting: this.naturalSorting
                }
            });
        },

        // @deprecated tag:v6.4.0.0
        getListingParams() {
            if (this.disableRouteParams) {
                return {
                    limit: this.limit,
                    page: this.page,
                    term: this.term,
                    sortBy: this.sortBy,
                    sortDirection: this.sortDirection,
                    naturalSorting: this.naturalSorting
                };
            }
            // Get actual query parameter
            const query = this.$route.query;

            const params = {
                limit: query.limit,
                page: query.page,
                term: query.term,
                sortBy: query.sortBy || this.sortBy,
                sortDirection: query.sortDirection || this.sortDirection,
                naturalSorting: query.naturalSorting || this.naturalSorting
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

        updateSelection(selection) {
            this.selection = selection;
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
            if (value.length === 0) {
                value = undefined;
            }
            this.term = value;

            if (this.disableRouteParams) {
                this.page = 1;
                this.getList();
                return;
            }

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
                    sortDirection: 'ASC',
                    naturalSorting: column.naturalSorting
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
