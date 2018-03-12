import { Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';

/**
 * @module app/mixin/productList
 */
Mixin.register('productList', {
    data() {
        return {
            isLoading: false,
            products: [],
            total: 0,
            sortBy: 'name',
            sortDirection: 'ASC',
            term: '',
            filters: []
        };
    },

    mounted() {
        this.getProductList();
    },

    methods: {
        getProductList() {
            this.isLoading = true;

            const terms = [];
            this.filters.forEach((filter) => {
                if (!filter.active) {
                    return;
                }
                const criteria = filter.criteria;
                const term = CriteriaFactory[criteria.type](criteria.field, criteria.options);
                terms.push(term);
            });

            const criterias = CriteriaFactory.nested(
                'AND',
                ...terms
            );

            return this.$store.dispatch('product/getProductList', {
                offset: this.offset,
                limit: this.limit,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
                term: this.term,
                criterias: criterias.getQueryString()
            }).then((response) => {
                this.total = response.total;
                this.products = response.products;
                this.isLoading = false;

                return this.products;
            });
        }
    }
});
