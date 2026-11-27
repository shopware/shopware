/**
 * @sw-package inventory
 */
import { mapPropertyErrors } from 'src/app/service/map-errors.service';
import template from './sw-product-price-card-form.html.twig';
import './sw-product-price-card-form.scss';

type Tax = { id: string; name: string; taxRate: number };

type CurrencyPrice = { currencyId: string; gross: number | null; net: number | null; linked: boolean };

/**
 * Shows the tax rate, display prices and purchase prices of the product prices card,
 * each price section with its own inheritance.
 * Replaces `sw-product-price-form` as the extension point of the prices card.
 *
 * @public
 */
export default Shopware.Component.wrapComponentConfig({
    template,

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
        isLoading(): boolean {
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

        showModeSetting(): boolean {
            return Shopware.Store.get('swProductDetail').showModeSetting;
        },

        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        taxes(): Tax[] {
            return Shopware.Store.get('swProductDetail').taxes as Tax[];
        },

        currencies() {
            return Shopware.Store.get('swProductDetail').currencies;
        },

        ...mapPropertyErrors('product', [
            'taxId',
            'price',
            'purchasePrices',
        ]),

        taxRateHelpText(): string {
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

        taxRateOptions(): { id: string; value: string; label: string }[] {
            const formatter = new Intl.NumberFormat(Shopware.Store.get('session').currentLocale ?? undefined, {
                style: 'percent',
                maximumFractionDigits: 3,
            });

            return this.taxes.map((tax) => ({
                id: tax.id,
                value: tax.id,
                label: `${this.getTaxLabel(tax)} (${formatter.format(tax.taxRate / 100)})`,
            }));
        },

        displayPricesInherited(): boolean {
            return !!this.parentProduct.id && this.isPriceInherited(this.product.price as CurrencyPrice[] | null);
        },

        displayPrices() {
            return this.displayPricesInherited ? this.parentProduct.price : this.product.price;
        },
    },

    methods: {
        getTaxLabel(tax: Tax): string {
            const translationKey = `global.tax-rates.${tax.name}`;

            return this.$te(translationKey) ? this.$t(translationKey) : tax.name;
        },

        isPriceInherited(prices: CurrencyPrice[] | null | undefined): boolean {
            return !prices?.length;
        },

        copyDefaultPrice(prices: CurrencyPrice[] | null): CurrencyPrice[] {
            return this.copyDefaultCurrencyPrice(prices, null);
        },

        copyDefaultPurchasePrice(prices: CurrencyPrice[] | null): CurrencyPrice[] {
            return this.copyDefaultCurrencyPrice(prices, 0);
        },

        /** @private */
        copyDefaultCurrencyPrice(prices: CurrencyPrice[] | null, fallbackValue: number | null): CurrencyPrice[] {
            const currencyId = this.defaultCurrency.id as string;
            const price = prices?.find((item) => item.currencyId === currencyId) ?? {
                currencyId,
                gross: fallbackValue,
                net: fallbackValue,
                linked: true,
            };

            return [Shopware.Utils.object.cloneDeep(price)];
        },

        onMaintainCurrenciesClose(prices: CurrencyPrice[]): void {
            if (!this.displayPricesInherited) {
                this.product.price = prices as typeof this.product.price;
            }

            this.displayMaintainCurrencies = false;
        },

        updatePrices(index: number): void {
            (this.product.price as CurrencyPrice[]).splice(index, 1);
        },
    },
});
