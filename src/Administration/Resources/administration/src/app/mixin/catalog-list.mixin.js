import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/catalogList
 */
Mixin.register('catalogList', {
    data() {
        return {
            catalogs: [],
            totalCatalogs: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getCatalogList();
    },

    methods: {
        getCatalogList(offset = 0, limit = 25) {
            this.isLoading = true;

            return this.$store.dispatch('catalog/getList', offset, limit).then((response) => {
                this.totalCatalogs = response.total;
                this.catalogs = response.items;
                this.isLoading = false;

                return this.catalogs;
            });
        }
    }
});
