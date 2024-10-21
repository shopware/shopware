import template from './sw-order-detail-general.html.twig';

/**
 * @package checkout
 */

const { Utils, Mixin } = Shopware;
const { format, array } = Utils;
const { mapGetters, mapState } = Shopware.Component.getComponentHelper();
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: {
        swOrderDetailOnSaveAndReload: {
            from: 'swOrderDetailOnSaveAndReload',
            default: null,
        },
        swOrderDetailOnSaveEdits: {
            from: 'swOrderDetailOnSaveEdits',
            default: null,
        },
        swOrderDetailOnRecalculateAndReload: {
            from: 'swOrderDetailOnRecalculateAndReload',
            default: null,
        },
        swOrderDetailOnSaveAndRecalculate: {
            from: 'swOrderDetailOnSaveAndRecalculate',
            default: null,
        },
        acl: {
            from: 'acl',
            default: null,
        },
    },

    emits: [
        'save-and-recalculate',
        'save-edits',
        'recalculate-and-reload',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        orderId: {
            type: String,
            required: true,
        },

        isSaveSuccessful: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            shippingCosts: null,
        };
    },

    computed: {
        ...mapGetters('swOrderDetail', [
            'isLoading',
        ]),

        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
        ]),

        delivery() {
            return this.order.deliveries[0];
        },

        deliveryDiscounts() {
            return array.slice(this.order.deliveries, 1) || [];
        },

        shippingCostsDetail() {
            const calcTaxes = this.sortByTaxRate(cloneDeep(this.order.shippingCosts.calculatedTaxes));
            const formattedTaxes = `${calcTaxes
                .map(
                    (calcTax) =>
                        `${this.$tc('sw-order.detailBase.shippingCostsTax', 0, {
                            taxRate: calcTax.taxRate,
                            tax: format.currency(calcTax.tax, this.order.currency.isoCode),
                        })}`,
                )
                .join('<br>')}`;

            return `${this.$tc('sw-order.detailBase.tax')}<br>${formattedTaxes}`;
        },

        sortedCalculatedTaxes() {
            return this.sortByTaxRate(cloneDeep(this.order.price.calculatedTaxes)).filter((price) => price.tax !== 0);
        },

        taxStatus() {
            return this.order.price.taxStatus;
        },

        displayRounded() {
            return (
                this.order.totalRounding.interval !== 0.01 ||
                this.order.totalRounding.decimals !== this.order.itemRounding.decimals
            );
        },

        orderTotal() {
            if (this.displayRounded) {
                return this.order.price.rawTotal;
            }

            return this.order.price.totalPrice;
        },

        currency() {
            return this.order.currency;
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },

    methods: {
        sortByTaxRate(price) {
            return price.sort((prev, current) => {
                return prev.taxRate - current.taxRate;
            });
        },

        onShippingChargeEdited() {
            this.delivery.shippingCosts.unitPrice = this.shippingCosts;
            this.delivery.shippingCosts.totalPrice = this.shippingCosts;

            this.saveAndRecalculate();
        },

        onShippingChargeUpdated(amount) {
            this.shippingCosts = amount;
        },

        saveAndRecalculate() {
            this.$emit('save-and-recalculate');

            if (this.swOrderDetailOnSaveAndRecalculate) {
                this.swOrderDetailOnSaveAndRecalculate();
            }
        },

        onSaveEdits() {
            this.$emit('save-edits');

            if (this.swOrderDetailOnSaveEdits) {
                this.swOrderDetailOnSaveEdits();
            }
        },

        recalculateAndReload() {
            this.$emit('recalculate-and-reload');

            if (this.swOrderDetailOnRecalculateAndReload) {
                this.swOrderDetailOnRecalculateAndReload();
            }
        },
    },
};
