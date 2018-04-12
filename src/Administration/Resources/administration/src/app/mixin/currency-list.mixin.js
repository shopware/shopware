import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/currencyList
 */
Mixin.register('currencyList', {
    data() {
        return {
            currencies: [],
            totalCurrencies: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getCurrencyList();
    },

    methods: {
        getCurrencyList(offset = 0, limit = 25) {
            this.isLoading = true;

            return this.$store.dispatch('currency/getCurrencyList', offset, limit).then((response) => {
                this.totalCurrencies = response.total;
                this.currencies = response.currencies;
                this.isLoading = false;

                return this.currencies;
            });
        }
    }
});
