import { Component, Application } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import './sw-order-detail-base.scss';

import EntityStore from 'src/core/data/EntityStore';
import EntityProxy from 'src/core/data/EntityProxy';
import template from './sw-order-detail-base.html.twig';


Component.register('sw-order-detail-base', {
    template,
    inject: ['orderService', 'stateStyleDataProviderService'],
    props: {
        order: {
            type: Object,
            required: true
        },
        attributeSets: {
            type: Array,
            required: true
        }
    },
    data() {
        return {
            isLoading: true,
            isEditing: false,
            isDisplayingLeavePageWarning: false,
            hasAssociations: false,
            lastVersionId: null,
            nextRoute: null,
            currentOrder: null,
            transactionOptions: [],
            orderOptions: [],
            liveVersionId: ''
        };
    },
    computed: {
        shippingCostsDetail() {
            const calcTaxes = this.sortByTaxRate(this.currentOrder.shippingCosts.calculatedTaxes);
            const formattedTaxes = `${calcTaxes.map(
                calcTax => `${this.$tc('sw-order.detailBase.shippingCostsTax', 0, {
                    taxRate: calcTax.taxRate,
                    tax: format.currency(calcTax.tax, this.currentOrder.currency.shortName)
                })}`
            ).join('<br>')}`;

            return `${this.$tc('sw-order.detailBase.tax')}<br>${formattedTaxes}`;
        },
        sortedCalculatedTaxes() {
            return this.sortByTaxRate(this.currentOrder.price.calculatedTaxes);
        },
        documentStore() {
            return this.currentOrder.getAssociation('documents');
        },
        transactionOptionPlaceholder() {
            if (this.isLoading) return null;

            return `${this.$tc('sw-order.stateCard.headlineTransactionState')}: \
            ${this.currentOrder.transactions[0].stateMachineState.translated.name}`;
        },
        transactionOptionsBackground() {
            if (this.isLoading) return null;

            return this.stateStyleDataProviderService.getStyle('order_transaction.state',
                this.currentOrder.transactions[0].stateMachineState.technicalName).selectBackgroundStyle;
        },
        orderOptionPlaceholder() {
            if (this.isLoading) return null;

            return `${this.$tc('sw-order.stateCard.headlineOrderState')}: \
            ${this.currentOrder.stateMachineState.translated.name}`;
        },
        orderOptionsBackground() {
            if (this.isLoading) return null;

            return this.stateStyleDataProviderService.getStyle('order.state',
                this.currentOrder.stateMachineState.technicalName).selectBackgroundStyle;
        }
    },
    created() {
        this.createdComponent();
    },
    beforeRouteLeave(to, from, next) {
        if (this.isEditing) {
            this.nextRoute = next;
            this.isDisplayingLeavePageWarning = true;
        } else {
            next();
        }
    },
    methods: {
        createdComponent() {
            this.isLoading = true;

            this.liveVersionId = Application.getContainer('init').contextService.liveVersionId;
            this.recalculationOrderStore = new EntityStore(this.order.getEntityName(),
                this.orderService,
                EntityProxy);

            this.recalculationOrderStore.add(this.order);
            this.currentOrder = this.order;
            this.reloadOrderAssociations();
        },
        createVersionedOrder() {
            this.isLoading = true;

            this.orderService.versionize(this.order.id).then((response) => {
                const tmpVersionId = response.data.versionId;
                this.reloadVersionedOrder(tmpVersionId);
            }).catch((error) => {
                this.$emit('sw-order-detail-base-error', error);
            });
        },
        reloadVersionedOrder(versionId) {
            this.isLoading = true;

            return this.recalculationOrderStore.getByIdAsync(this.order.id, '', versionId).then((entity) => {
                this.currentOrder = entity;
                this.$refs['sw-order-line-item-grid'].getList();
                return this.reloadOrderAssociations();
            });
        },
        reloadOrderAssociations() {
            this.isLoading = true;

            const addresses = this.currentOrder.getAssociation('addresses').getList(
                { page: 1, limit: 50, versionId: this.currentOrder.versionId }
            );

            const deliveries = this.currentOrder.getAssociation('deliveries').getList(
                { page: 1, limit: 50, versionId: this.currentOrder.versionId }
            );

            const transactions = this.currentOrder.getAssociation('transactions').getList(
                { page: 1, limit: 50, versionId: this.currentOrder.versionId }
            );

            const documents = this.currentOrder.getAssociation('documents').getList(
                { page: 1, limit: 50, versionId: this.currentOrder.versionId }
            );

            return Promise.all([addresses, deliveries, transactions, documents]).then(() => {
                this.isLoading = false;
                this.hasAssociations = true;
                return Promise.resolve();
            });
        },
        saveAndReloadVersionedOrder() {
            return this.currentOrder.save().then(() => {
                return this.reloadVersionedOrder(this.currentOrder.versionId);
            });
        },
        loadLiveVersion() {
            this.reloadVersionedOrder(this.liveVersionId);
        },
        startEditing() {
            if (this.currentOrder.versionId === this.liveVersionId) {
                this.createVersionedOrder();
            }
            this.isEditing = true;
        },
        cancelEditing() {
            this.isEditing = false;
            this.loadLiveVersion();
        },
        mergeOrder() {
            this.isLoading = true;
            this.orderService.mergeVersion(this.currentOrder.id, this.currentOrder.versionId).then(() => {
                this.cancelEditing();
            }).catch((error) => {
                this.$emit('sw-order-detail-base-error', error);
            });
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
        onRecalculateOrder() {
            this.isLoading = true;

            this.orderService.recalculateOrder(this.currentOrder.id, this.currentOrder.versionId, {}, {}).then(() => {
                this.reloadVersionedOrder(this.currentOrder.versionId);
            }).catch((error) => {
                this.$emit('sw-order-detail-base-error', error);
            });
        },
        onShippingChargeEdited(amount) {
            this.currentOrder.deliveries[0].shippingCosts.unitPrice = amount;
            this.currentOrder.deliveries[0].shippingCosts.totalPrice = amount;
            return this.currentOrder.save().then(() => {
                this.onLineItemChanges();
            });
        },
        sortByTaxRate(price) {
            return price.sort((prev, current) => {
                return prev.taxRate - current.taxRate;
            });
        },
        changeLanguage() {
            this.reloadVersionedOrder(this.currentOrder.versionId);
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
        }
    }
});
