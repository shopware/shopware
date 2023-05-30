import template from './sw-order-detail-general.html.twig';

/**
 * @package customer-order
 */

const { Utils, Mixin } = Shopware;
const { format, array } = Utils;
const { mapGetters, mapState } = Shopware.Component.getComponentHelper();
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
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
            const formattedTaxes = `${calcTaxes.map(
                calcTax => `${this.$tc('sw-order.detailBase.shippingCostsTax', 0, {
                    taxRate: calcTax.taxRate,
                    tax: format.currency(calcTax.tax, this.order.currency.shortName),
                })}`,
            ).join('<br>')}`;

            return `${this.$tc('sw-order.detailBase.tax')}<br>${formattedTaxes}`;
        },

        sortedCalculatedTaxes() {
            return this.sortByTaxRate(cloneDeep(this.order.price.calculatedTaxes))
                .filter(price => price.tax !== 0);
        },

        taxStatus() {
            return this.order.price.taxStatus;
        },

        displayRounded() {
            return this.order.totalRounding.interval !== 0.01
                || this.order.totalRounding.decimals !== this.order.itemRounding.decimals;
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
    },

    methods: {
        sortByTaxRate(price) {
            return price.sort((prev, current) => {
                return prev.taxRate - current.taxRate;
            });
        },

        onShippingChargeEdited(amount) {
            this.delivery.shippingCosts.unitPrice = amount;
            this.delivery.shippingCosts.totalPrice = amount;
            this.saveAndRecalculate();
        },

        saveAndRecalculate() {
            this.$emit('save-and-recalculate');
        },

        onSaveEdits() {
            this.$emit('save-edits');
        },

        recalculateAndReload() {
            this.$emit('recalculate-and-reload');
        },
    },
};
