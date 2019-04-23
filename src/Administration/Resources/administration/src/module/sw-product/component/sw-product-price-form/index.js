import { Component, Mixin } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

Component.register('sw-product-price-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    computed: {
        productTaxRate() {
            if (!this.taxes.items) {
                return {};
            }

            return Object.values(this.taxes.items).find((taxRate) => {
                return taxRate.id === this.product.taxId;
            });
        },

        defaultCurrency() {
            if (!this.currencies.items) {
                return [];
            }

            return Object.values(this.currencies.items).find((currency) => {
                return currency.isDefault;
            });
        },

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        ...mapState('swProductDetail', [
            'product',
            'taxes',
            'currencies'
        ])
    }
});
