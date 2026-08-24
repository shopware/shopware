/**
 * @sw-package discovery
 */

import type { PropType } from 'vue';
import template from './sw-settings-customer-group-price-preview.html.twig';
import './sw-settings-customer-group-price-preview.scss';

type PriceDisplayMode = 'gross' | 'net' | 'grossNetBase';

type EmphasisedRow = 'total' | 'merchant';

interface PricePreviewExample {
    key: string;
    countryLabel: string;
    taxRate: number;
    displayedPrice: number;
    taxAmount: number;
    totalPrice: number;
    merchantPrice: number;
}

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        mode: {
            type: String as PropType<PriceDisplayMode>,
            required: true,
        },

        netPrice: {
            type: Number,
            required: false,
            default: 10,
        },

        grossPrice: {
            type: Number,
            required: false,
            default: 11.9,
        },

        taxRates: {
            type: Array as PropType<number[]>,
            required: false,
            default: () => [
                19,
                20,
            ],
        },
    },

    computed: {
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        currencyIsoCode(): string {
            return Shopware.Context.app.systemCurrencyISOCode ?? 'EUR';
        },

        countryLabels(): string[] {
            return [
                this.$t('sw-settings-customer-group.priceDisplay.preview.countryA'),
                this.$t('sw-settings-customer-group.priceDisplay.preview.countryB'),
            ];
        },

        showsGrossPrices(): boolean {
            return this.mode !== 'net';
        },

        displayedPriceNote(): string {
            return this.showsGrossPrices
                ? this.$t('sw-settings-customer-group.priceDisplay.preview.inclVat')
                : this.$t('sw-settings-customer-group.priceDisplay.preview.exclVat');
        },

        fixedRow(): EmphasisedRow {
            return this.mode === 'gross' ? 'total' : 'merchant';
        },

        varyingRow(): EmphasisedRow {
            return this.mode === 'gross' ? 'merchant' : 'total';
        },

        hint(): string {
            if (this.mode === 'gross') {
                return this.$t('sw-settings-customer-group.priceDisplay.preview.hintGross');
            }

            if (this.mode === 'net') {
                return this.$t('sw-settings-customer-group.priceDisplay.preview.hintNet');
            }

            return this.$t('sw-settings-customer-group.priceDisplay.preview.hintGrossNetBase');
        },

        examples(): PricePreviewExample[] {
            return this.taxRates.map((taxRate, index) => {
                const taxFactor = 1 + taxRate / 100;
                const merchantPrice = this.round(this.mode === 'gross' ? this.grossPrice / taxFactor : this.netPrice);
                const totalPrice = this.round(this.mode === 'gross' ? this.grossPrice : this.netPrice * taxFactor);

                return {
                    key: `tax-rate-${taxRate}`,
                    countryLabel: this.countryLabels[index] ?? '',
                    taxRate,
                    displayedPrice: this.showsGrossPrices ? totalPrice : merchantPrice,
                    taxAmount: this.round(totalPrice - merchantPrice),
                    totalPrice,
                    merchantPrice,
                };
            });
        },
    },

    methods: {
        round(value: number): number {
            return Math.round(value * 100) / 100;
        },

        formatPrice(value: number): string {
            return this.currencyFilter(value, this.currencyIsoCode) as string;
        },

        rowClasses(row: EmphasisedRow): Record<string, boolean> {
            return {
                'is--fixed': this.fixedRow === row,
                'is--varying': this.varyingRow === row,
            };
        },
    },
});
