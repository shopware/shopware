import { Component, State } from 'src/core/shopware';
import template from './sw-settings-currency-list.html.twig';

Component.register('sw-settings-currency-list', {
    template,

    mixins: [
        'listing'
    ],

    data() {
        return {
            currencies: [],
            isLoading: false
        };
    },

    computed: {
        currencyStore() {
            return State.getStore('currency');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.currencies = [];

            return this.currencyStore.getList(params).then((response) => {
                this.total = response.total;
                this.currencies = response.items;
                this.isLoading = false;

                return this.products;
            });
        }
    }
});
