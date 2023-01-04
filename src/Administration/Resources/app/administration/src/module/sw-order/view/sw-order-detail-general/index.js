import template from './sw-order-detail-general.html.twig';

/**
 * @package customer-order
 */

const { Component, Utils, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { format, array } = Utils;
const { mapGetters, mapState } = Shopware.Component.getComponentHelper();
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-detail-general', {
    template,

    inject: [
        'repositoryFactory',
        'orderService',
        'stateStyleDataProviderService',
        'acl',
        'feature',
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
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
            transactionOptions: [],
            orderOptions: [],
            deliveryOptions: [],
            customFieldSets: [],
            promotions: [],
            promotionError: null,
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

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderLineItemRepository() {
            return this.repositoryFactory.create('order_line_item');
        },

        delivery() {
            return this.order.deliveries[0];
        },

        deliveryDiscounts() {
            return array.slice(this.order.deliveries, 1) || [];
        },

        transaction() {
            for (let i = 0; i < this.order.transactions.length; i += 1) {
                if (!['cancelled', 'failed'].includes(this.order.transactions[i].stateMachineState.technicalName)) {
                    return this.order.transactions[i];
                }
            }
            return this.order.transactions.last();
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

        transactionOptionPlaceholder() {
            if (this.isLoading) return null;

            return `${this.$tc('sw-order.stateCard.headlineTransactionState')}: \
            ${this.transaction.stateMachineState.translated.name}`;
        },

        transactionOptionsBackground() {
            if (this.isLoading) {
                return null;
            }

            return this.stateStyleDataProviderService.getStyle('order_transaction.state',
                this.transaction.stateMachineState.technicalName).selectBackgroundStyle;
        },

        orderOptionPlaceholder() {
            if (this.isLoading) {
                return null;
            }

            return `${this.$tc('sw-order.stateCard.headlineOrderState')}: \
            ${this.order.stateMachineState.translated.name}`;
        },

        orderOptionsBackground() {
            if (this.isLoading) {
                return null;
            }

            return this.stateStyleDataProviderService.getStyle('order.state',
                this.order.stateMachineState.technicalName).selectBackgroundStyle;
        },

        deliveryOptionPlaceholder() {
            if (this.isLoading) {
                return null;
            }

            return `${this.$tc('sw-order.stateCard.headlineDeliveryState')}: \
            ${this.delivery.stateMachineState.translated.name}`;
        },

        deliveryOptionsBackground() {
            if (this.isLoading) {
                return null;
            }

            return this.stateStyleDataProviderService.getStyle('order_delivery.state',
                this.delivery.stateMachineState.technicalName).selectBackgroundStyle;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addAssociation('customFields');
            criteria.getAssociation('customFields')
                .addSorting(Criteria.naturalSorting('config.customFieldPosition'));
            criteria.addFilter(Criteria.equals('relations.entityName', 'order'));

            return criteria;
        },

        disabledAutoPromotionVisibility: {
            get() {
                return !this.hasAutomaticPromotions;
            },
            set(state) {
                this.toggleAutomaticPromotions(state);
            },
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

        hasLineItem() {
            return this.order.lineItems.filter(item => item.hasOwnProperty('id')).length > 0;
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

        onStateTransitionOptionsChanged(stateMachineName, options) {
            if (stateMachineName === 'order.states') {
                this.orderOptions = options;
            } else if (stateMachineName === 'order_transaction.states') {
                this.transactionOptions = options;
            } else if (stateMachineName === 'order_delivery.states') {
                this.deliveryOptions = options;
            }
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
});
