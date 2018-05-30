import { Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';

/**
 * @module app/mixin/orderList
 */
Mixin.register('orderList', {
    data() {
        return {
            orders: [],
            offset: 0,
            limit: 25,
            totalOrders: 0,
            sortBy: null,
            sortDirection: 'ASC',
            term: '',
            filters: [],
            isLoading: false
        };
    },

    mounted() {
        this.getOrderList();
    },

    methods: {
        /**
         * Generates criterias based on the provided filters using the {@link CriteriaFactory}
         *
         * @param {Array} filters
         * @param {String} [operator='AND']
         * @returns {null|Object}
         */
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

        getDataFromRoute() {
            const params = this.$route.params;

            this.offset = params.offset || this.offset;
            this.limit = params.limit || this.limit;
            this.sortDirection = params.sortDirection || this.sortDirection;
            this.sortBy = params.sortBy || this.sortBy;
            this.term = params.term || this.term;

            return params;
        },

        getListingParams() {
            const params = {
                limit: this.limit,
                offset: this.offset
            };

            if (this.term && this.term.length) {
                params.term = this.term;
            }

            if (this.sortBy && this.sortBy.length) {
                params.sortBy = this.sortBy;
                params.sortDirection = this.sortDirection;
            }

            return params;
        },

        /**
         * Requests the order list from the API using the {@link module:app/state/order} state module.
         *
         * @returns {Promise<any>}
         */
        getOrderList() {
            this.isLoading = true;

            const params = this.getListingParams();
            const criterias = this.generateCriteriaFromFilters(this.filters);

            if (criterias) {
                params.criterias = [criterias.getQuery()];
            }

            this.orders = [];

            return this.$store.dispatch('order/getOrderList', params).then((response) => {
                this.totalOrders = response.total;
                this.orders = response.orders;
                this.isLoading = false;

                return this.orders;
            });
        }
    }
});
