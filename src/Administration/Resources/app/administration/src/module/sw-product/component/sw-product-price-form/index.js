import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-price-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            displayMaintainCurrencies: false,
        };
    },

    computed: {
        ...mapGetters('swProductDetail', [
            'isLoading',
            'defaultPrice',
            'defaultCurrency',
            'productTaxRate',
            'showModeSetting',
        ]),

        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'taxes',
            'currencies',
        ]),

        ...mapPropertyErrors('product', ['taxId', 'price', 'purchasePrices']),

        taxRateHelpText() {
            const link = {
                name: 'sw.settings.tax.index',
            };

            return this.$tc('sw-product.priceForm.taxRateHelpText.label', 0, {
                link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$tc('sw-product.priceForm.taxRateHelpText.linkText')}
                      </sw-internal-link>`,
            });
        },

        prices: {
            get() {
                const prices = {
                    price: [],
                    purchasePrices: [],
                };

                if (this.product && Array.isArray(this.product.price)) {
                    prices.price = [...this.product.price];
                }

                if (this.product && Array.isArray(this.product.purchasePrices)) {
                    prices.purchasePrices = [...this.product.purchasePrices];
                }

                return prices;
            },

            set(newValue) {
                this.product.price = (newValue?.price) || null;
                this.product.purchasePrices = (newValue?.purchasePrices) || null;
            },
        },

        parentPrices() {
            return {
                price: this.product.price || this.parentProduct.price,
                purchasePrices: this.product.purchasePrices || this.parentProduct.purchasePrices,
            };
        },
    },

    methods: {
        removePriceInheritation(refPrice) {
            const defaultRefPrice = refPrice.price?.find((price) => price.currencyId === this.defaultCurrency.id);
            const defaultRefPurchasePrice = refPrice.purchasePrices?.find(
                (price) => price.currencyId === this.defaultCurrency.id,
            );

            const prices = {
                price: [],
                purchasePrices: [],
            };

            if (defaultRefPrice) {
                prices.price.push({
                    currencyId: defaultRefPrice.currencyId,
                    gross: defaultRefPrice.gross,
                    net: defaultRefPrice.net,
                    linked: defaultRefPrice.linked,
                });
            }

            if (defaultRefPurchasePrice) {
                prices.purchasePrices.push({
                    currencyId: defaultRefPurchasePrice.currencyId,
                    gross: defaultRefPurchasePrice.gross,
                    net: defaultRefPurchasePrice.net,
                    linked: defaultRefPurchasePrice.linked,
                });
            }

            return prices;
        },

        inheritationCheckFunction() {
            return !this.prices.price.length && !this.prices.purchasePrices.length;
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
        },
    },
});
