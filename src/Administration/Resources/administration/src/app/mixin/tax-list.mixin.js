import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/taxList
 */
Mixin.register('taxList', {
    data() {
        return {
            taxes: [],
            totalTaxes: 0,
            limit: 25,
            total: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getTaxList();
    },

    methods: {
        getTaxList() {
            this.isLoading = true;

            return this.$store.dispatch('tax/getTaxList', this.offset, this.limit).then((response) => {
                this.totalTaxes = response.total;
                this.taxes = response.taxes;
                this.isLoading = false;

                return this.taxes;
            });
        }
    }
});
