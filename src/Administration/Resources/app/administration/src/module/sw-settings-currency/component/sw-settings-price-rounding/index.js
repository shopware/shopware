import template from './sw-settings-price-rounding.html.twig';
import './sw-settings-price-rounding.scss';

const { Component } = Shopware;

Component.register('sw-settings-price-rounding', {
    template,

    props: {
        itemRounding: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },

        totalRounding: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
    },

    data() {
        return {
            intervalOptions: [
                { label: this.$tc('sw-settings-currency.price-rounding.labelIntervalNone'), value: 0.01 },
                { label: '0.05', value: 0.05 },
                { label: '0.10', value: 0.10 },
                { label: '0.50', value: 0.50 },
                { label: '1.00', value: 1 },
            ],
        };
    },

    computed: {
        itemIntervalDisabled() {
            return this.itemRounding.decimals > 2;
        },
        totalIntervalDisabled() {
            return this.totalRounding.decimals > 2;
        },
        showHeaderInfo() {
            return this.totalRounding.interval !== 0.01
                || this.itemRounding.decimals !== this.totalRounding.decimals;
        },
        showHeaderWarning() {
            return this.totalRounding.interval !== this.itemRounding.interval;
        },
    },

    methods: {
        /**
         * @param {number} decimals
         * @param {string} type - Either be itemRounding or totalRounding
         */
        onChangeDecimals(decimals, type) {
            if (decimals <= 2 || !['itemRounding', 'totalRounding'].includes(type)) {
                return;
            }

            this[type].interval = 0.01;
        },
    },
});
