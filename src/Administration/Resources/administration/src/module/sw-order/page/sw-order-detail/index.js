import { Application, Component, Mixin } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';

Component.register('sw-order-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context',
        'orderService',
        'stateStyleDataProviderService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            order: null,
            orderId: null,
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
            isEditing: false,
            isLoading: false,
            isSaveSuccessful: false,
            transactionOptions: [],
            orderOptions: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.order !== null ? this.order.orderNumber : '';
        },

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

            criteria.addAssociationPaths([
                'lineItems',
                'currency',
                'orderCustomer',
                'salesChannel.language',
                'addresses.country',
                'deliveries.shippingMethod',
                'deliveries.shippingOrderAddress',
                'transactions.paymentMethod',
                'documents',
                'tags'
            ]);

            return criteria;
        }
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
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
            this.orderId = this.$route.params.id;
            this.versionContext = this.context;
            this.reloadEntityData();
        },

        reloadEntityData() {
            this.isLoading = true;

            return this.orderRepository.get(this.orderId, this.versionContext, this.orderCriteria).then((response) => {
                this.order = response;
                this.isLoading = false;
                return Promise.resolve();
            }).catch(() => {
                this.isLoading = false;
                return Promise.reject();
            });
        },

        saveAndReload() {
            this.isLoading = true;
            return this.orderRepository.save(this.order, this.versionContext).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.onError(error);
            }).finally(() => {
                this.isLoading = false;
                return Promise.resolve();
            });
        },

        recalculateAndReload() {
            this.isLoading = true;
            return this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {}).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.onError(error);
            }).finally(() => {
                this.isLoading = false;
                return Promise.resolve();
            });
        },

        saveAndRecalculate() {
            this.isLoading = true;
            return this.orderRepository.save(this.order, this.versionContext).then(() => {
                return this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {});
            }).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.onError(error);
            })
                .finally(() => {
                    this.isLoading = false;
                    return Promise.resolve();
                });
        },

        onChangeLanguage() {
            this.reloadEntityData();
        },

        saveEditsFinish() {
            this.isSaveSuccessful = false;
            this.isEditing = false;
        },

        onStartEditing() {
            this.isLoading = true;

            this.orderRepository.createVersion(this.orderId, this.versionContext).then((newContext) => {
                this.versionContext = newContext;
                return this.reloadEntityData();
            }).then(() => {
                this.isEditing = true;
                return Promise.resolve();
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onSaveEdits() {
            this.isLoading = true;
            this.isEditing = false;

            this.orderRepository.mergeVersion(this.versionContext.versionId, this.versionContext).catch((error) => {
                this.onError(error);
            }).finally(() => {
                this.versionContext.versionId = Application.getContainer('init').contextService.liveVersionId;
                this.reloadEntityData();
            });
        },

        onCancelEditing() {
            this.isLoading = true;

            this.orderRepository.deleteVersion(
                this.orderId,
                this.versionContext.versionId,
                this.versionContext
            ).catch((error) => {
                // This error has no consequences, because we revert to the live version anyways
                this.onError(error);
            });

            this.versionContext.versionId = Application.getContainer('init').contextService.liveVersionId;
            this.reloadEntityData().then(() => {
                this.isEditing = false;
            });
        },

        onError(error) {
            let errorDetails = null;

            try {
                errorDetails = error.response.data.errors[0].detail;
            } catch (e) {
                errorDetails = '';
            }

            this.createNotificationError({
                title: this.$tc('sw-order.detail.titleRecalculationError'),
                message: this.$tc('sw-order.detail.messageRecalculationError') + errorDetails
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
