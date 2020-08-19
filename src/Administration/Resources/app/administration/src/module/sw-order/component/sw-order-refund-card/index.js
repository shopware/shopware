import './sw-order-refund-card.scss';
import { cloneDeep, flatMap, groupBy, round } from 'lodash';
import template from './sw-order-refund-card.html.twig';

const { Component, Mixin, Utils, Filter } = Shopware;
const { Criteria } = Shopware.Data;

const REFUND_STATE_OPEN = 'open';
const REFUND_STATE_COMPLETED = 'completed';
const REFUND_STATE_IN_PROGRESS = 'in_progress';
const CAPTURE_STATE_COMPLETED = 'completed';

Component.register('sw-order-refund-card', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'repositoryFactory',
        'orderRefundService',
        'stateStyleDataProviderService'
    ],
    props: {
        title: {
            type: String,
            required: true
        },
        order: {
            type: Object,
            required: true
        },
        transaction: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showModal: false,
            orderRefundPositions: [],
            paymentMethodRefundConfigs: null,
            refundOptions: {},
            isRefundSuccessful: false,
            refundAmount: 0,
            isRefundAmountEdited: false,
            isRefunding: false,
            selectedCaptureId: null
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        dateFilter() {
            return Filter.getByName('date');
        },

        currencyFilter() {
            return Filter.getByName('currency');
        },

        orderRefundRepository() {
            return this.repositoryFactory.create('order_refund');
        },

        orderRefundPositionRepository() {
            return this.repositoryFactory.create('order_refund_position');
        },

        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        paymentMethodRefundConfigRepository() {
            return this.repositoryFactory.create('payment_method_refund_config');
        },

        refundingUnsupportedByPaymentMethod() {
            return !this.transaction.paymentMethod.refundHandlerIdentifier;
        },

        selectedCapture() {
            if (!this.selectedCaptureId) {
                return null;
            }

            return this.eligibleCapturesForRefund.find(capture => capture.id === this.selectedCaptureId);
        },

        totalSelectedCaptureAmount() {
            if (!this.selectedCaptureId) {
                return 0;
            }

            return this.selectedCapture.amount;
        },

        remainingAmount() {
            return this.totalSelectedCaptureAmount - this.refundedAmount;
        },

        refundedAmount() {
            if (!this.selectedCaptureId) {
                return 0;
            }
            const refundedAmount = this.order.refunds
                .filter(orderRefund => orderRefund.stateMachineState.technicalName === REFUND_STATE_COMPLETED)
                .filter(orderRefund => orderRefund.transactionCaptureId === this.selectedCaptureId)
                .reduce((refundedAmountAccumulator, refund) => {
                    return refundedAmountAccumulator + refund.amount;
                }, 0);

            return round(refundedAmount, this.order.currency.decimalPrecision);
        },

        captures() {
            return this.transaction.captures || [];
        },

        capturesWithLabel() {
            return this.captures
                .map(capture => {
                    let label = `${this.formatDate(capture.createdAt)} (${this.formatCurrency(
                        capture.amount,
                        this.order.currency.shortName,
                        this.order.currency.decimalPrecision
                    )})`;
                    if (capture.externalReference) {
                        label += ` - ${capture.externalReference}`;
                    }

                    return {
                        ...capture,
                        label
                    };
                });
        },

        completedCaptures() {
            return this.capturesWithLabel
                .filter(capture => capture.stateMachineState.technicalName === CAPTURE_STATE_COMPLETED);
        },

        eligibleCapturesForRefund() {
            const completedOrInProgressRefunds = this.order.refunds.filter(orderRefund => {
                const orderRefundStateTechnicalName = orderRefund.stateMachineState.technicalName;

                return orderRefundStateTechnicalName === REFUND_STATE_COMPLETED
                    || orderRefundStateTechnicalName === REFUND_STATE_IN_PROGRESS;
            });
            const completedOrInProgressRefundsByCaptureId = groupBy(
                completedOrInProgressRefunds,
                refund => refund.transactionCaptureId
            );

            return this.completedCaptures.filter(capture => {
                const refundsForCapture = completedOrInProgressRefundsByCaptureId[capture.id] || [];
                const alreadyRefundedAmount = refundsForCapture.reduce(
                    (accumulator, refund) => accumulator + refund.amount,
                    0
                );
                const remainingAmountToRefund = round(
                    capture.amount - alreadyRefundedAmount,
                    this.order.currency.decimalPrecision
                );

                return remainingAmountToRefund > 0;
            });
        },

        orderRefundColumns() {
            return [
                {
                    property: 'createdAt',
                    label: this.$tc('sw-order.refundCard.refunds.columns.createdAt'),
                    rawData: true
                },
                {
                    property: 'paymentMethod',
                    label: this.$tc('sw-order.refundCard.refunds.columns.paymentMethod'),
                    rawData: true
                },
                {
                    property: 'refundAmount',
                    label: this.$tc('sw-order.refundCard.refunds.columns.refundAmount'),
                    rawData: true,
                    align: 'right'
                },
                {
                    property: 'state',
                    label: this.$tc('sw-order.refundCard.refunds.columns.state'),
                    rawData: true
                }
            ];
        },

        paymentMethodRefundOptions() {
            if (!this.paymentMethodRefundConfigs) {
                return [];
            }

            return flatMap(this.paymentMethodRefundConfigs, config => config.options);
        },

        invalidRefundOptions() {
            return this.paymentMethodRefundOptions
                .filter(option => option.required)
                .filter(option => this.refundOptions[option.name] === undefined
                    || this.refundOptions[option.name] === null
                    || this.refundOptions[option.name] === '');
        }
    },

    methods: {
        createdComponent() {
            this.orderRefundPositions = this.createOrderRefundPositions();
            if (this.eligibleCapturesForRefund.length > 0) {
                this.selectedCaptureId = this.eligibleCapturesForRefund[0].id;
            }
        },

        formatDate(dateTime) {
            return this.dateFilter(dateTime, { hour: '2-digit', minute: '2-digit' });
        },

        formatCurrency(value, currencyName, decimalPlaces) {
            return this.currencyFilter(value, currencyName, decimalPlaces);
        },

        async onCreateRefund() {
            this.refundAmount = 0;
            this.isRefundAmountEdited = false;
            this.orderRefundPositions = this.createOrderRefundPositions();
            this.showModal = true;
            this.isRefundSuccessful = false;
            await this.ensurePaymentMethodRefundConfigs();
        },

        closeModal() {
            this.showModal = false;
        },

        onRefundFinished() {
            this.isRefundSuccessful = false;
            this.closeModal();
            this.$nextTick(() => {
                this.$emit('order-state-change');
            });
        },

        onChangeRefundAmount(refundAmount) {
            this.refundAmount = refundAmount;
            this.isRefundAmountEdited = true;
        },

        onSelectItem(id, selected) {
            const orderRefundPosition = this.getOrderRefundPositionById(id);
            orderRefundPosition.selected = selected;

            if (!this.isRefundAmountEdited) {
                this.updateRefundAmount();
            }
        },

        onChangeQuantity(id, quantity) {
            const orderRefundPosition = this.getOrderRefundPositionById(id);
            orderRefundPosition.refundPrice.quantity = quantity;
            orderRefundPosition.refundPrice.totalPrice = round(
                orderRefundPosition.refundPrice.unitPrice * quantity,
                this.order.currency.decimalPrecision
            );

            if (!this.isRefundAmountEdited) {
                this.updateRefundAmount();
            }
        },

        getOrderRefundPositionById(id) {
            return this.orderRefundPositions.find(
                orderRefundPosition => orderRefundPosition.id === id
            );
        },

        createOrderRefundPositions() {
            return this.order.lineItems.map(lineItem => ({
                selected: false,
                id: Utils.createId(),
                lineItemId: lineItem.id,
                label: lineItem.label,
                payload: cloneDeep(lineItem.payload),
                lineItemPrice: cloneDeep(lineItem.price),
                refundPrice: cloneDeep(lineItem.price)
            }));
        },

        updateRefundAmount() {
            const selectedOrderRefundPositionsAmount = this.orderRefundPositions
                .filter(orderRefundPosition => orderRefundPosition.selected)
                .reduce((refundAmount, orderRefundPosition) => {
                    return refundAmount + orderRefundPosition.refundPrice.totalPrice;
                }, 0);

            this.refundAmount = Math.min(
                round(selectedOrderRefundPositionsAmount, this.order.currency.decimalPrecision),
                this.remainingAmount
            );
        },

        getSelectedOrderRefundPositions() {
            return this.orderRefundPositions
                .filter(orderRefundPosition => orderRefundPosition.selected)
                .map(orderRefundPositionPrototype => {
                    const orderRefundPosition = this.orderRefundPositionRepository.create(Shopware.Context.api);
                    orderRefundPosition.lineItemId = orderRefundPositionPrototype.lineItemId;
                    orderRefundPosition.payload = orderRefundPositionPrototype.payload;
                    orderRefundPosition.label = orderRefundPositionPrototype.label;
                    orderRefundPosition.lineItemPrice = orderRefundPositionPrototype.lineItemPrice;
                    orderRefundPosition.refundPrice = orderRefundPositionPrototype.refundPrice;

                    return orderRefundPosition;
                });
        },

        async ensurePaymentMethodRefundConfigs() {
            if (this.paymentMethodRefundConfigs !== null) {
                return;
            }
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('paymentMethodId', this.transaction.paymentMethodId));
            this.isLoading = true;
            try {
                this.paymentMethodRefundConfigs = await this.paymentMethodRefundConfigRepository.search(
                    criteria,
                    Shopware.Context.api
                );
                this.createRefundOptionsWithDefaultValues();
            } finally {
                this.isLoading = false;
            }
        },

        createRefundOptionsWithDefaultValues() {
            this.paymentMethodRefundOptions
                .filter(option => option.default !== undefined)
                .forEach(option => {
                    this.$set(this.refundOptions, option.name, option.default);
                });
        },

        async getOrderRefundState(technicalName) {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals(
                'state_machine_state.stateMachine.technicalName',
                'order_refund.state'
            ));
            criteria.addFilter(Criteria.equals(
                'state_machine_state.technicalName',
                technicalName
            ));

            const stateMachineStates = await this.stateMachineStateRepository.search(criteria, Shopware.Context.api);

            return stateMachineStates.first();
        },

        getVariantFromOrderRefundState(orderRefund) {
            return this.stateStyleDataProviderService.getStyle(
                'order_refund.state', orderRefund.stateMachineState.technicalName
            ).variant;
        },

        async refundOrderTransaction() {
            if (this.refundAmount > this.remainingAmount) {
                this.createNotificationError({
                    title: 'Error',
                    message: 'Refund amount can not be higher than remaining amount. '
                        + `Tried to refund ${this.refundAmount} ${this.order.currency.symbol}, `
                        + `but the maximum is ${this.remainingAmount} ${this.order.currency.symbol}.`
                });
                return;
            }
            if (this.invalidRefundOptions.length > 0) {
                this.createNotificationError({
                    title: 'Error',
                    message: 'There are required fields, please fill them first.'
                });
                return;
            }
            this.isRefunding = true;
            this.isRefundSuccessful = false;
            try {
                const orderRefund = this.orderRefundRepository.create(Shopware.Context.api);
                orderRefund.orderId = this.order.id;
                orderRefund.transactionId = this.transaction.id;
                orderRefund.paymentMethodId = this.transaction.paymentMethodId;
                orderRefund.amount = this.refundAmount;
                orderRefund.options = this.refundOptions;
                const openState = await this.getOrderRefundState(REFUND_STATE_OPEN);
                orderRefund.stateId = openState.id;
                const positions = this.getSelectedOrderRefundPositions();
                positions.forEach(position => {
                    orderRefund.positions.add(position);
                });
                if (this.selectedCaptureId) {
                    orderRefund.transactionCaptureId = this.selectedCaptureId;
                }

                await this.orderRefundRepository.save(orderRefund, Shopware.Context.api);
                await this.orderRefundService.process(orderRefund.id);
                this.isRefundSuccessful = true;
                this.createNotificationSuccess({
                    title: 'Success',
                    message: `Successfully refunded ${orderRefund.amount} ${this.order.currency.symbol}`
                });
            } catch (err) {
                let errorMessage = err.message;
                if (err.response && err.response.data) {
                    errorMessage = err.response && err.response.data;
                    if (err.response.data.errors && err.response.data.errors.length > 0) {
                        errorMessage = err.response.data.errors.map(error => error.detail).join(', ');
                    }
                }
                this.createNotificationError({
                    title: 'Error',
                    message: `Error refunding ${this.refundAmount} ${this.order.currency.symbol}: ${errorMessage}`
                });
                this.onRefundFinished();
            } finally {
                this.isRefunding = false;
            }
        },

        onRefundConfigurationFieldChange(optionName, value) {
            this.$set(this.refundOptions, optionName, value);
        }
    }
});
