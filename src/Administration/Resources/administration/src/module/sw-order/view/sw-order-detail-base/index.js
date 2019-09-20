import template from './sw-order-detail-base.html.twig';

const { Component, Application } = Shopware;
const { Criteria } = Shopware.Data;
const format = Shopware.Utils.format;

Component.register('sw-order-detail-base', {
    template,

    inject: [
        'repositoryFactory',
        'context',
        'orderService',
        'stateStyleDataProviderService'
    ],

    props: {
        orderId: {
            type: String,
            required: true
        },

        isLoading: {
            type: Boolean,
            required: true
        },

        isEditing: {
            type: Boolean,
            required: true
        },

        isSaveSuccessful: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            order: null,
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
            transactionOptions: [],
            orderOptions: [],
            versionContext: null
        };
    },

    beforeRouteLeave(to, from, next) {
        if (this.isEditing) {
            this.nextRoute = next;
            this.isDisplayingLeavePageWarning = true;
        } else {
            next();
        }
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        delivery() {
            return this.order.deliveries[0];
        },

        shippingCostsDetail() {
            const calcTaxes = this.sortByTaxRate(this.order.shippingCosts.calculatedTaxes);
            const formattedTaxes = `${calcTaxes.map(
                calcTax => `${this.$tc('sw-order.detailBase.shippingCostsTax', 0, {
                    taxRate: calcTax.taxRate,
                    tax: format.currency(calcTax.tax, this.order.currency.shortName)
                })}`
            ).join('<br>')}`;

            return `${this.$tc('sw-order.detailBase.tax')}<br>${formattedTaxes}`;
        },

        sortedCalculatedTaxes() {
            return this.sortByTaxRate(this.order.price.calculatedTaxes).filter(price => price.tax !== 0);
        },

        transactionOptionPlaceholder() {
            if (this.isLoading) return null;

            return `${this.$tc('sw-order.stateCard.headlineTransactionState')}: \
            ${this.order.transactions[0].stateMachineState.translated.name}`;
        },

        transactionOptionsBackground() {
            if (this.isLoading) {
                return null;
            }

            return this.stateStyleDataProviderService.getStyle('order_transaction.state',
                this.order.transactions[0].stateMachineState.technicalName).selectBackgroundStyle;
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

        orderCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria
                .addAssociation('lineItems')
                .addAssociation('currency')
                .addAssociation('orderCustomer')
                .addAssociation('language')
                .addAssociation('salesChannel')
                .addAssociation('addresses.country')
                .addAssociation('deliveries.shippingMethod')
                .addAssociation('deliveries.shippingOrderAddress')
                .addAssociation('transactions.paymentMethod')
                .addAssociation('documents.documentType')
                .addAssociation('tags');

            return criteria;
        }
    },

    watch: {
        orderId() {
            this.createdComponent();
        },

        'order.orderNumber'() {
            this.emitIdentifier();
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.versionContext = this.context;
            this.reloadEntityData();

            this.$root.$on('language-change', this.reloadEntityData);
            this.$root.$on('order-edit-start', this.onStartEditing);
            this.$root.$on('order-edit-save', this.onSaveEdits);
            this.$root.$on('order-edit-cancel', this.onCancelEditing);
        },

        destroyedComponent() {
            this.$root.$off('language-change', this.reloadEntityData);
            this.$root.$on('order-edit-start', this.onStartEditing);
            this.$root.$on('order-edit-save', this.onSaveEdits);
            this.$root.$on('order-edit-cancel', this.onCancelEditing);
        },

        reloadEntityData() {
            this.$emit('loading-change', true);

            return this.orderRepository.get(this.orderId, this.versionContext, this.orderCriteria).then((response) => {
                this.order = response;
                this.$emit('loading-change', false);
                return Promise.resolve();
            }).catch(() => {
                this.$emit('loading-change', false);
                return Promise.reject();
            });
        },

        emitIdentifier() {
            const orderNumber = this.order !== null ? this.order.orderNumber : '';
            this.$emit('identifier-change', orderNumber);
        },

        saveAndReload() {
            this.$emit('loading-change', true);
            return this.orderRepository.save(this.order, this.versionContext).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.$emit('error', error);
            }).finally(() => {
                this.$emit('loading-change', false);
                return Promise.resolve();
            });
        },

        recalculateAndReload() {
            this.$emit('loading-change', true);
            return this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {}).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.$emit('error', error);
            }).finally(() => {
                this.$emit('loading-change', false);
                return Promise.resolve();
            });
        },

        saveAndRecalculate() {
            this.$emit('loading-change', true);
            return this.orderRepository.save(this.order, this.versionContext).then(() => {
                return this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {});
            }).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.$emit('error', error);
            })
                .finally(() => {
                    this.$emit('loading-change', false);
                    return Promise.resolve();
                });
        },

        onStartEditing() {
            this.$emit('loading-change', true);

            this.orderRepository.createVersion(this.orderId, this.versionContext).then((newContext) => {
                this.versionContext = newContext;
                return this.reloadEntityData();
            }).then(() => {
                this.$emit('editing-change', true);
                return Promise.resolve();
            }).finally(() => {
                this.$emit('loading-change', false);
            });
        },

        onSaveEdits() {
            this.$emit('loading-change', true);
            this.$emit('editing-change', false);

            this.orderRepository.mergeVersion(this.versionContext.versionId, this.versionContext).catch((error) => {
                this.$emit('error', error);
            }).finally(() => {
                this.versionContext.versionId = Application.getContainer('init').contextService.liveVersionId;
                this.reloadEntityData();
            });
        },

        onCancelEditing() {
            this.$emit('loading-change', true);

            this.orderRepository.deleteVersion(
                this.orderId,
                this.versionContext.versionId,
                this.versionContext
            ).catch((error) => {
                // This error has no consequences, because we revert to the live version anyways
                this.$emit('error', error);
            });

            this.versionContext.versionId = Application.getContainer('init').contextService.liveVersionId;
            this.reloadEntityData().then(() => {
                this.$emit('editing-change', false);
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
            }
        },

        onQuickOrderStatusChange(actionName) {
            this.$refs['state-card'].onOrderStateSelected(actionName);
        },

        onQuickTransactionStatusChange(actionName) {
            this.$refs['state-card'].onTransactionStateSelected(actionName);
        },

        onLeaveModalClose() {
            this.nextRoute(false);
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm() {
            this.isDisplayingLeavePageWarning = false;

            this.$nextTick(() => {
                this.nextRoute();
            });
        }
    }
});
