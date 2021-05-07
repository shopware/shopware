import template from './sw-product-bulk-edit-modal.html.twig';

const { Component } = Shopware;

Component.register('sw-product-bulk-edit-modal', {
    template,

    props: {
        selection: {
            type: Object,
            required: false,
            default: null
        },

        bulkGridEditColumns: {
            type: Array,
            required: true
        },

        currencies: {
            type: Array,
            required: true
        }
    },

    methods: {
        getCurrencyPriceByCurrencyId(currencyId, prices) {
            const priceForProduct = prices.find(price => price.currencyId === currencyId);

            if (priceForProduct) {
                return priceForProduct;
            }

            return {
                currencyId: null,
                gross: null,
                linked: true,
                net: null
            };
        }
    }
});
