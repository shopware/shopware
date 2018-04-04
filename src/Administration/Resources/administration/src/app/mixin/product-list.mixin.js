import { Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';

/**
 * @module app/mixin/productList
 */
Mixin.register('productList', {
    data() {
        return {
            products: [],
            offset: 0,
            limit: 25,
            totalProducts: 0,
            sortBy: null,
            sortDirection: 'ASC',
            term: '',
            filters: [],
            isLoading: false
        };
    },

    mounted() {
        this.getProductList();
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

        /**
         * Requests the product list from the API using the {@link module:app/state/product} state module.
         *
         * @returns {Promise<any>}
         */
        getProductList() {
            this.isLoading = true;

            const criterias = this.generateCriteriaFromFilters(this.filters);
            const config = {
                offset: this.offset,
                limit: this.limit,
                sortDirection: this.sortDirection
            };

            if (this.term && this.term.length > 0) {
                config.term = this.term;
            }

            if (this.sortBy && this.sortBy.length > 0) {
                config.sortBy = this.sortBy;
            }

            if (criterias) {
                config.criterias = [criterias.getQuery()];
            }

            return this.$store.dispatch('product/getProductList', config).then((response) => {
                this.totalProducts = response.total;
                this.products = response.products;
                this.isLoading = false;

                return this.products;
            });
        }
    }
});
