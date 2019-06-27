import { Component, Mixin } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
import { mapApiErrors } from 'src/app/service/map-errors.service';
import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

Component.register('sw-product-price-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            displayMaintainCurrencies: false
        };
    },

    computed: {
        ...mapGetters('swProductDetail', [
            'isLoading',
            'defaultPrice',
            'defaultCurrency',
            'productTaxRate'
        ]),

        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'taxes',
            'currencies'
        ]),

        ...mapApiErrors('product', ['taxId', 'price', 'purchasePrice', 'purchaseUnit', 'referenceUnit', 'packUnit']),

        maintainCurrencyColumns() {
            return [
                {
                    property: 'name',
                    label: '',
                    visible: true,
                    allowResize: false,
                    primary: true,
                    rawData: false,
                    width: '150px'
                }, {
                    property: 'price',
                    label: this.$tc('sw-product.priceForm.columnPrice'),
                    visible: true,
                    allowResize: false,
                    primary: true,
                    rawData: false
                }
            ];
        }
    },

    methods: {
        getPriceForCurrencyId(currencyId) {
            if (!this.product.price || !this.currencies) {
                return null;
            }

            const foundPrice = this.product.price.find((price) => {
                return price.currencyId === currencyId;
            });

            return foundPrice !== undefined ? foundPrice : null;
        },

        isCurrencyInherited(currency) {
            const priceForCurrency = this.product.price.find((price) => {
                return price.currencyId === currency.id;
            });

            return !priceForCurrency;
        },

        onInheritanceRestore(currencyId) {
            // create entry for currency in product price
            const indexOfPrice = this.product.price.findIndex((price) => {
                return price.currencyId === currencyId;
            });

            this.$delete(this.product.price, indexOfPrice);
        },

        onInheritanceRemove(currency) {
            // create new entry for currency in product price
            this.$set(this.product.price, this.product.price.length, {
                currencyId: currency.id,
                gross: this.convertPrice(this.defaultPrice.gross, currency),
                linked: this.defaultPrice.linked,
                net: this.convertPrice(this.defaultPrice.net, currency)
            });
        },

        convertPrice(value, currency) {
            const calculatedPrice = value * currency.factor;
            const priceRounded = calculatedPrice.toFixed(currency.decimalPrecision);
            return Number(priceRounded);
        },

        removePriceInheritation(refPrice) {
            const defaultRefPrice = refPrice.find((price) => price.currencyId === this.defaultCurrency.id);

            return [{
                currencyId: defaultRefPrice.currencyId,
                gross: defaultRefPrice.gross,
                net: defaultRefPrice.net,
                linked: defaultRefPrice.linked
            }];
        }
    }
});
