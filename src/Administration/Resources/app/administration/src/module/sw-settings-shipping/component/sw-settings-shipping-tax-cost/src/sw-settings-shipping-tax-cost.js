import template from './sw-settings-shipping-tax-cost.html.twig';
import { Criteria, mapPropertyErrors, mapState, placeholderMixin, useShippingDetailStore } from '../dependencies';

/**
 * @sw-package checkout
 *
 * @public
 */
export default {
    template,

    mixins: [placeholderMixin],

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
        ...mapState(
            () => useShippingDetailStore(),
            [
                'shippingMethod',
                'currencies',
                'defaultCurrency',
                'usedRules',
                'unrestrictedPriceMatrixExists',
                'newPriceMatrixExists',
            ],
        ),

        ...mapPropertyErrors('shippingMethod', ['taxType', 'taxId']),

        shippingCostTaxOptions() {
            return [
                {
                    label: this.$t('sw-settings-shipping.shippingCostOptions.auto'),
                    value: 'auto',
                },
                {
                    label: this.$t('sw-settings-shipping.shippingCostOptions.highest'),
                    value: 'highest',
                },
                {
                    label: this.$t('sw-settings-shipping.shippingCostOptions.fixed'),
                    value: 'fixed',
                },
            ];
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
                return this.$t(`global.tax-rates.${tax.name}`);
            }

            return tax.name;
        },
    },
};
