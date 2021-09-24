import template from './sw-order-detail-details.html.twig';

const { Component, Utils, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { array } = Utils;
const { mapGetters, mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-order-detail-details', {
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
            showStateHistoryModal: false,
        };
    },

    computed: {
        ...mapGetters('swOrderDetail', [
            'isLoading',
            'isEditing',
        ]),

        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
        ]),

        delivery() {
            return this.order.deliveries.length > 0 && this.order.deliveries[0];
        },

        deliveryDiscounts() {
            return array.slice(this.order.deliveries, 1) || [];
        },

        transaction() {
            for (let i = 0; i < this.order.transactions.length; i += 1) {
                if (this.order.transactions[i].stateMachineState.technicalName !== 'cancelled') {
                    return this.order.transactions[i];
                }
            }
            return this.order.transactions.last();
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
            const customFieldsCriteria = new Criteria(1, 100);
            customFieldsCriteria.addSorting(Criteria.sort('config.customFieldsPosition'));

            const criteria = new Criteria(1, 100);
            criteria.addAssociation('customFields', customFieldsCriteria);
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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((result) => {
                this.customFieldSets = result;
            });
        },

        onShippingChargeEdited(amount) {
            this.delivery.shippingCosts.unitPrice = amount;
            this.delivery.shippingCosts.totalPrice = amount;

            this.saveAndRecalculate();
        },

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

        onQuickOrderStatusChange(actionName) {
            this.$refs['state-card'].onOrderStateSelected(actionName);
        },

        onQuickTransactionStatusChange(actionName) {
            this.$refs['state-card'].onTransactionStateSelected(actionName);
        },

        onQuickDeliveryStatusChange(actionName) {
            this.$refs['state-card'].onDeliveryStateSelected(actionName);
        },

        saveAndRecalculate() {
            this.$emit('save-and-recalculate');
        },

        recalculateAndReload() {
            this.$emit('recalculate-and-reload');
        },

        saveAndReload() {
            this.$emit('save-and-reload=');
        },
    },
});
