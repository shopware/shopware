import template from './sw-order-detail-general.html.twig';

/**
 * @sw-package checkout
 */

const { Utils, Mixin, Store } = Shopware;
const { format, array } = Utils;
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
        swOrderDetailOnReloadEntityData: {
            from: 'swOrderDetailOnReloadEntityData',
            default: null,
        },
        swOrderDetailOnError: {
            from: 'swOrderDetailOnError',
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
        'save-and-reload',
        'reload-entity-data',
        'error',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        orderId: {
            type: String,
            required: true,
        },

        /** @deprecated tag:v6.8.0 - will be removed without replacement */
        isSaveSuccessful: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            shippingCosts: null,
        };
    },

    computed: {
        /** @deprecated tag:v6.8.0 - will be removed, use loading.order instead */
        isLoading: () => Store.get('swOrderDetail').isLoading,

        loading: () => Store.get('swOrderDetail').loading,

        order: () => Store.get('swOrderDetail').order,

        versionContext: () => Store.get('swOrderDetail').versionContext,

        delivery() {
            if (!Shopware.Feature.isActive('v6.8.0.0')) {
                return this.order.deliveries[0];
            }

            return this.order.primaryOrderDelivery;
        },

        deliveryDiscounts() {
            if (!Shopware.Feature.isActive('v6.8.0.0')) {
                return array.slice(this.order.deliveries, 1) || [];
            }

            return this.order.deliveries.filter((delivery) => delivery.id !== this.order.primaryOrderDeliveryId);
        },

        shippingCostsDetail() {
            const calcTaxes = this.sortByTaxRate(cloneDeep(this.order.shippingCosts.calculatedTaxes));
            const formattedTaxes = `${calcTaxes
                .map(
                    (calcTax) =>
                        `${this.$tc(
                            'sw-order.detailBase.shippingCostsTax',
                            {
                                taxRate: calcTax.taxRate,
                                tax: format.currency(calcTax.tax, this.order.currency.isoCode),
                            },
                            0,
                        )}`,
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
            if (this.shippingCosts >= 0) {
                this.delivery.shippingCosts.unitPrice = this.shippingCosts;
                this.delivery.shippingCosts.totalPrice = this.shippingCosts;
            }

            this.saveAndRecalculate();
        },

        onShippingChargeUpdated(amount) {
            this.shippingCosts = amount;
        },

        saveAndRecalculate() {
            if (this.swOrderDetailOnSaveAndRecalculate) {
                this.swOrderDetailOnSaveAndRecalculate();
            } else {
                this.$emit('save-and-recalculate');
            }
        },

        onSaveEdits() {
            if (this.swOrderDetailOnSaveEdits) {
                this.swOrderDetailOnSaveEdits();
            } else {
                this.$emit('save-edits');
            }
        },

        recalculateAndReload() {
            if (this.swOrderDetailOnRecalculateAndReload) {
                this.swOrderDetailOnRecalculateAndReload();
            } else {
                this.$emit('recalculate-and-reload');
            }
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        updateLoading(loadingValue) {
            Store.get('swOrderDetail').setLoading([
                'order',
                loadingValue,
            ]);
        },

        reloadEntityData() {
            if (this.swOrderDetailOnReloadEntityData) {
                this.swOrderDetailOnReloadEntityData();
            } else {
                this.$emit('reload-entity-data');
            }
        },

        saveAndReload() {
            if (this.swOrderDetailOnSaveAndReload) {
                this.swOrderDetailOnSaveAndReload();
            } else {
                this.$emit('save-and-reload');
            }
        },

        showError(error) {
            if (this.swOrderDetailOnError) {
                this.swOrderDetailOnError(error);
            } else {
                this.$emit('error', error);
            }
        },
    },
};
