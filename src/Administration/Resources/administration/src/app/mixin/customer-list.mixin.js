import { Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';

/**
 * @module app/mixin/customerList
 */
Mixin.register('customerList', {
    data() {
        return {
            customers: [],
            offset: 0,
            limit: 25,
            totalCustomers: 0,
            sortBy: null,
            sortDirection: 'ASC',
            term: '',
            filters: [],
            isLoading: false
        };
    },

    mounted() {
        this.getCustomerList();
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
         * Requests the customer list from the API using the {@link module:app/state/customer} state module.
         *
         * @returns {Promise<any>}
         */
        getCustomerList() {
            this.isLoading = true;

            const params = this.getListingParams();
            const criterias = this.generateCriteriaFromFilters(this.filters);

            if (criterias) {
                params.criterias = [criterias.getQuery()];
            }

            this.customers = [];

            return this.$store.dispatch('customer/getCustomerList', params).then((response) => {
                this.totalCustomers = response.total;
                this.customers = response.customers;
                this.isLoading = false;

                return this.customers;
            });
        }
    }
});
