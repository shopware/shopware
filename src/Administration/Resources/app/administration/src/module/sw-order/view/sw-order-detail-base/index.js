import template from './sw-order-detail-base.html.twig';

const { Component, Utils } = Shopware;
const { Criteria } = Shopware.Data;
const { format, array } = Utils;

Component.register('sw-order-detail-base', {
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

        isLoading: {
            type: Boolean,
            required: true,
        },

        isEditing: {
            type: Boolean,
            required: true,
        },

        isSaveSuccessful: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            order: null,
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
            transactionOptions: [],
            orderOptions: [],
            deliveryOptions: [],
            versionContext: null,
            customFieldSets: [],
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

        shippingCostsDetail() {
            const calcTaxes = this.sortByTaxRate(this.order.shippingCosts.calculatedTaxes);
            const formattedTaxes = `${calcTaxes.map(
                calcTax => `${this.$tc('sw-order.detailBase.shippingCostsTax', 0, {
                    taxRate: calcTax.taxRate,
                    tax: format.currency(calcTax.tax, this.order.currency.shortName),
                })}`,
            ).join('<br>')}`;

            return `${this.$tc('sw-order.detailBase.tax')}<br>${formattedTaxes}`;
        },

        sortedCalculatedTaxes() {
            return this.sortByTaxRate(this.order.price.calculatedTaxes).filter(price => price.tax !== 0);
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

        orderCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria
                .addAssociation('currency')
                .addAssociation('orderCustomer')
                .addAssociation('language');

            criteria
                .getAssociation('lineItems')
                .addFilter(Criteria.equals('parentId', null))
                .addSorting(Criteria.sort('position', 'ASC'));

            criteria
                .getAssociation('lineItems.children')
                .addSorting(Criteria.naturalSorting('label'));

            criteria
                .getAssociation('deliveries')
                .addSorting(Criteria.sort('shippingCosts.unitPrice', 'DESC'));

            criteria
                .addAssociation('salesChannel');

            criteria
                .addAssociation('addresses.country')
                .addAssociation('addresses.countryState')
                .addAssociation('deliveries.shippingMethod')
                .addAssociation('deliveries.shippingOrderAddress')
                .addAssociation('transactions.paymentMethod')
                .addAssociation('documents.documentType')
                .addAssociation('tags');

            criteria.getAssociation('transactions').addSorting(Criteria.sort('createdAt'));

            return criteria;
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
    },

    watch: {
        orderId() {
            this.createdComponent();
        },

        'order.orderNumber'() {
            this.emitIdentifier();
        },

        'order.createdById'() {
            this.emitCreatedById();
        },
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.versionContext = Shopware.Context.api;
            this.reloadEntityData();

            this.$root.$on('language-change', this.reloadEntityData);
            this.$root.$on('order-edit-start', this.onStartEditing);
            this.$root.$on('order-edit-save', this.onSaveEdits);
            this.$root.$on('order-edit-cancel', this.onCancelEditing);

            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((result) => {
                this.customFieldSets = result;
            });
        },

        destroyedComponent() {
            this.$root.$off('language-change', this.reloadEntityData);
            this.$root.$off('order-edit-start', this.onStartEditing);
            this.$root.$off('order-edit-save', this.onSaveEdits);
            this.$root.$off('order-edit-cancel', this.onCancelEditing);
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

        emitCreatedById() {
            const createdById = this.order !== null ? this.order.createdById : '';
            this.$emit('created-by-id-change', createdById);
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

            this.orderRepository.save(this.order, this.versionContext)
                .then(() => {
                    return this.orderRepository.mergeVersion(this.versionContext.versionId, this.versionContext);
                }).catch((error) => {
                    this.$emit('error', error);
                }).finally(() => {
                    this.versionContext.versionId = Shopware.Context.api.liveVersionId;
                    this.reloadEntityData();
                });
        },

        onCancelEditing() {
            this.$emit('loading-change', true);

            this.orderRepository.deleteVersion(
                this.orderId,
                this.versionContext.versionId,
                this.versionContext,
            ).catch((error) => {
                // This error has no consequences, because we revert to the live version anyways
                this.$emit('error', error);
            });

            this.versionContext.versionId = Shopware.Context.api.liveVersionId;
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
        },
    },
});
