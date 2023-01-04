import template from './sw-order-detail-details.html.twig';
import './sw-order-detail-details.scss';

/**
 * @package customer-order
 */

const { Component, State } = Shopware;
const { Criteria } = Shopware.Data;
const { array } = Shopware.Utils;
const { mapGetters, mapState } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-detail-details', {
    template,

    inject: [
        'repositoryFactory',
        'orderService',
        'stateStyleDataProviderService',
        'acl',
        'feature',
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
            showStateHistoryModal: false,
        };
    },

    computed: {
        ...mapGetters('swOrderDetail', [
            'isLoading',
        ]),

        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
            'orderAddressIds',
        ]),

        delivery() {
            return this.order.deliveries.length > 0 && this.order.deliveries[0];
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
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('relations.entityName', 'order'));

            return criteria;
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.order.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.order.salesChannelId));
            }

            return criteria;
        },

        paymentMethodCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.order.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.order.salesChannelId));
            }

            criteria.addFilter(Criteria.equals('afterOrderEnabled', 1));

            return criteria;
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

        billingAddress() {
            return this.order.addresses.find((address) => {
                return address.id === this.order.billingAddressId;
            });
        },

        shippingAddress() {
            return this.delivery.shippingOrderAddress;
        },

        selectedBillingAddressId() {
            const currentAddress = this.orderAddressIds.find(item => item.type === 'billing');
            return currentAddress?.customerAddressId || this.billingAddress.id;
        },

        selectedShippingAddressId() {
            const currentAddress = this.orderAddressIds.find(item => item.type === 'shipping');
            return currentAddress?.customerAddressId || this.shippingAddress.id;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('update-loading', true);

            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((result) => {
                this.customFieldSets = result;
                this.$emit('update-loading', false);
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

        saveAndReload() {
            this.$emit('save-and-reload');
        },

        onSaveEdits() {
            this.$emit('save-edits');
        },

        reloadEntityData() {
            this.$emit('reload-entity-data');
        },

        showError(error) {
            this.$emit('error', error);
        },

        updateLoading(loadingValue) {
            State.commit('swOrderDetail/setLoading', ['order', loadingValue]);
        },

        validateTrackingCode(searchTerm) {
            const trackingCode = searchTerm.trim();

            if (trackingCode.length <= 0) {
                return false;
            }

            const isExist = this.delivery?.trackingCodes?.find(code => code === trackingCode);
            return !isExist;
        },

        onChangeOrderAddress(value) {
            State.commit('swOrderDetail/setOrderAddressIds', value);
        },
    },
});
