import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/productList
 */
Mixin.register('productList', {
    data() {
        return {
            isLoading: false,
            products: [],
            total: 0
        };
    },

    mounted() {
        this.getProductList();
    },

    methods: {
        getProductList() {
            this.isLoading = true;

            return this.$store.dispatch('product/getProductList', this.offset, this.limit).then((response) => {
                this.total = response.total;
                this.products = response.products;
                this.isLoading = false;

                return this.products;
            });
        }
    }
});
