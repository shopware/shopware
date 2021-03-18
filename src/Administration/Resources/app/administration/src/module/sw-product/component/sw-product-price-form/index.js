import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-price-form', {
    template,

    inject: ['feature'],

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            displayMaintainCurrencies: false,
            limitPricesItem: 2
        };
    },

    computed: {
        ...mapGetters('swProductDetail', [
            'isLoading',
            'defaultPrice',
            'defaultCurrency',
            'productTaxRate',
            'showModeSetting'
        ]),

        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'taxes',
            'currencies'
        ]),

        ...mapPropertyErrors('product', ['taxId', 'price', 'purchasePrices']),

        taxRateHelpText() {
            const link = {
                name: 'sw.settings.tax.index'
            };

            return this.$tc('sw-product.priceForm.taxRateHelpText.label', 0, {
                link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$tc('sw-product.priceForm.taxRateHelpText.linkText')}
                      </sw-internal-link>`
            });
        },

        prices() {
            const prices = [];

            if (this.product && this.product.price) {
                prices.push(this.product.price);
            }

            if (this.product && this.product.purchasePrices) {
                prices.push(this.product.purchasePrices);
            }

            return prices;
        },

        parentPrices() {
            return [this.product.price || this.parentProduct.price, this.product.purchasePrices || this.parentProduct.purchasePrices];
        }
    },

    methods: {
        removePriceInheritation(refPrice) {
            const defaultRefPrice = refPrice.find((price) => price.currencyId === this.defaultCurrency.id);

            return [{
                currencyId: defaultRefPrice.currencyId,
                gross: defaultRefPrice.gross,
                net: defaultRefPrice.net,
                linked: defaultRefPrice.linked
            }];
        },

        inheritationCheckFunction() {
            return this.prices.length < this.limitPricesItem;
        },

        onMaintainCurrenciesClose(prices) {
            this.product.price = prices;

            this.displayMaintainCurrencies = false;
        },

        keymonitor(event) {
            if (event.key === ',') {
                const value = event.currentTarget.value;
                event.currentTarget.value = value.replace(/.$/, '.');
            }
        },

        getTaxLabel(tax) {
            if (this.$te(`global.tax-rates.${tax.name}`)) {
                return this.$tc(`global.tax-rates.${tax.name}`);
            }

            return tax.name;
        }
    }
});
