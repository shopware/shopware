import { Mixin } from 'src/core/shopware';

/**
 * @module app/mixin/CountryList
 */
Mixin.register('countryList', {
    data() {
        return {
            countries: [],
            totalCountries: 0,
            limit: 25,
            total: 0,
            isLoading: false
        };
    },

    mounted() {
        this.getCountryList();
    },

    methods: {
        getCountryList() {
            this.isLoading = true;

            return this.$store.dispatch('country/getCountryList', this.offset, this.limit).then((response) => {
                this.totalCountries = response.total;
                this.countries = response.items;
                this.isLoading = false;

                return this.countries;
            });
        }
    }
});
