import template from './sw-settings-shipping-tax-cost.html.twig';

const { Criteria } = Shopware.Data;
const { Component, Mixin } = Shopware;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-shipping-tax-cost', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        ...mapState('swShippingDetail', [
            'shippingMethod',
            'currencies',
        ]),

        ...mapGetters('swShippingDetail', [
            'defaultCurrency',
            'usedRules',
            'unrestrictedPriceMatrixExists',
            'newPriceMatrixExists',
        ]),

        ...mapPropertyErrors('shippingMethod', ['taxType', 'taxId']),

        shippingCostTaxOptions() {
            return [{
                label: this.$tc('sw-settings-shipping.shippingCostOptions.auto'),
                value: 'auto',
            }, {
                label: this.$tc('sw-settings-shipping.shippingCostOptions.highest'),
                value: 'highest',
            }, {
                label: this.$tc('sw-settings-shipping.shippingCostOptions.fixed'),
                value: 'fixed',
            }];
        },

        taxCriteria() {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('position'));

            return criteria;
        },
    },

    watch: {
        'shippingMethod.taxType'(val) {
            if (val !== 'fixed') {
                this.shippingMethod.taxId = '';
            }
        },
    },

    methods: {
        getTaxLabel(tax) {
            if (!tax) {
                return '';
            }

            if (this.$te(`global.tax-rates.${tax.name}`)) {
                return this.$tc(`global.tax-rates.${tax.name}`);
            }

            return tax.name;
        },
    },
});
