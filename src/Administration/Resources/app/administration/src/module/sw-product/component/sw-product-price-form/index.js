/*
 * @sw-package inventory
 */

import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

const { Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

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
        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        defaultPrice() {
            return Shopware.Store.get('swProductDetail').defaultPrice;
        },

        defaultCurrency() {
            return Shopware.Store.get('swProductDetail').defaultCurrency;
        },

        productTaxRate() {
            return Shopware.Store.get('swProductDetail').productTaxRate;
        },

        showModeSetting() {
            return Shopware.Store.get('swProductDetail').showModeSetting;
        },

        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        displayPricesInherited() {
            return !!this.parentProduct.id && this.isPriceInherited(this.product.price);
        },

        displayPrices() {
            if (this.displayPricesInherited) {
                return this.parentProduct.price;
            }

            return this.product.price;
        },

        taxes() {
            return Shopware.Store.get('swProductDetail').taxes;
        },

        currencies() {
            return Shopware.Store.get('swProductDetail').currencies;
        },

        currentLocale() {
            return Shopware.Store.get('session').currentLocale ?? 'en-GB';
        },

        ...mapPropertyErrors('product', ['taxId', 'price', 'purchasePrices']),

        taxRateHelpText() {
            const link = {
                name: 'sw.settings.tax.index',
            };

            return this.$t(
                'sw-product.priceForm.taxRateHelpText.label',
                {
                    link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$t('sw-product.priceForm.taxRateHelpText.linkText')}
                      </sw-internal-link>`,
                },
                0,
            );
        },

        /**
         * @deprecated tag:v6.8.0 - Use product.price and product.purchasePrices separately.
         */
        prices: {
            get() {
                Shopware.Feature.triggerDeprecationOrThrow(
                    'V6_8_0_0',
                    'sw-product-price-form.prices is deprecated. Use product.price and product.purchasePrices separately.',
                );

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
                Shopware.Feature.triggerDeprecationOrThrow(
                    'V6_8_0_0',
                    'sw-product-price-form.prices is deprecated. Use product.price and product.purchasePrices separately.',
                );

                this.product.price = newValue?.price || null;
                this.product.purchasePrices = newValue?.purchasePrices || null;
            },
        },

        /**
         * @deprecated tag:v6.8.0 - Use product.price, product.purchasePrices, parentProduct.price, and parentProduct.purchasePrices separately.
         */
        parentPrices() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-product-price-form.parentPrices is deprecated. Use the product and parentProduct price properties separately.',
            );

            return {
                price: this.product.price || this.parentProduct.price,
                purchasePrices: this.product.purchasePrices || this.parentProduct.purchasePrices,
            };
        },

        taxRateOptions() {
            const formatter = new Intl.NumberFormat(this.currentLocale, {
                style: 'percent',
                maximumFractionDigits: 20,
            });

            return this.taxes.map((tax) => {
                return {
                    id: tax.id,
                    value: tax.id,
                    label: `${this.getTaxLabel(tax)} (${formatter.format(tax.taxRate / 100)})`,
                };
            });
        },
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - Use copyDefaultPrice() or copyDefaultPurchasePrice() for the corresponding price section.
         */
        removePriceInheritation(refPrice) {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-product-price-form.removePriceInheritation() is deprecated. Use copyDefaultPrice() or copyDefaultPurchasePrice() instead.',
            );

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
                    listPrice: defaultRefPrice.listPrice ? defaultRefPrice.listPrice : null,
                    regulationPrice: defaultRefPrice.regulationPrice ? defaultRefPrice.regulationPrice : null,
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

        /**
         * @deprecated tag:v6.8.0 - Use isPriceInherited() for the corresponding price section.
         */
        inheritationCheckFunction() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-product-price-form.inheritationCheckFunction() is deprecated. Use isPriceInherited() instead.',
            );

            return !this.prices.price.length && !this.prices.purchasePrices.length;
        },

        onMaintainCurrenciesClose(prices) {
            if (!this.displayPricesInherited) {
                this.product.price = prices;
            }

            this.displayMaintainCurrencies = false;
        },

        getTaxLabel(tax) {
            if (this.$te(`global.tax-rates.${tax.name}`)) {
                return this.$t(`global.tax-rates.${tax.name}`);
            }

            return tax.name;
        },

        updatePrices(index) {
            this.product.price.splice(index, 1);
        },

        copyDefaultPrice(prices) {
            return this.copyDefaultCurrencyPrice(prices, null);
        },

        copyDefaultPurchasePrice(prices) {
            return this.copyDefaultCurrencyPrice(prices, 0);
        },

        copyDefaultCurrencyPrice(prices, fallbackValue) {
            const price = prices?.find(({ currencyId }) => currencyId === this.defaultCurrency.id) ?? {
                currencyId: this.defaultCurrency.id,
                gross: fallbackValue,
                net: fallbackValue,
                linked: true,
            };

            return [Shopware.Utils.object.cloneDeep(price)];
        },

        isPriceInherited(prices) {
            return !prices?.length;
        },
    },
};
