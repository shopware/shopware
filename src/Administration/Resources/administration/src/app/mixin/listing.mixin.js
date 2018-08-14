import { Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import { debug } from 'src/core/service/util.service';

Mixin.register('listing', {

    data() {
        return {
            page: 1,
            limit: 25,
            total: 0,
            sortBy: null,
            sortDirection: 'ASC',
            term: ''
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
        this.getDataFromRoute();
        this.updateRoute();
        this.getList();
    },

    methods: {
        getDataFromRoute() {
            const params = this.$route.query;

            this.page = params.page || this.page;
            this.limit = params.limit || this.limit;
            this.sortDirection = params.sortDirection || this.sortDirection;
            this.sortBy = params.sortBy || this.sortBy;
            this.term = params.term || this.term;

            if (params.filters) {
                const filters = JSON.parse(params.filters);

                filters.queries.forEach((query) => {
                    const localFilter = this.filters.find((filter) => {
                        return filter.criteria.type === query.type && filter.criteria.field === query.field;
                    });

                    if (localFilter) {
                        localFilter.active = true;
                    }
                });
            }

            return params;
        },

        updateRoute() {
            const params = this.getListingParams();

            if (params.criteria) {
                params.filters = params.criteria.getQueryString();
                delete params.criteria;
            }

            this.$router.push({
                name: this.routeName,
                query: params
            });
        },

        getListingParams() {
            const params = {
                limit: this.limit,
                page: this.page
            };

            if (this.term && this.term.length) {
                params.term = this.term;
            }

            if (this.sortBy && this.sortBy.length) {
                params.sortBy = this.sortBy;
                params.sortDirection = this.sortDirection;
            }

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

            return CriteriaFactory.nested(operator, ...terms);
        },

        onPageChange(opts) {
            this.page = opts.page;
            this.limit = opts.limit;

            this.updateRoute();
            this.getList();
        },

        onSearch(value) {
            this.term = value;

            this.page = 1;
            this.updateRoute();
            this.getList();
        },

        onSwitchFilter(filter, filterIndex) {
            this.filters[filterIndex].active = !this.filters[filterIndex].active;

            this.page = 1;
            this.updateRoute();
            this.getList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
            } else {
                this.sortBy = column.dataIndex;
                this.sortDirection = 'ASC';
            }

            this.updateRoute();
            this.getList();
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
