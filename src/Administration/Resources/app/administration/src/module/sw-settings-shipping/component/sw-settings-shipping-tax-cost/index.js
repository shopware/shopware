import template from './sw-settings-shipping-tax-cost.html.twig';

const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('position'));

            return criteria;
        },

        taxType: {
            get() {
                return this.shippingMethod.taxType || 'auto';
            },
            set(taxType) {
                this.shippingMethod.taxType = taxType;
            },
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
};
