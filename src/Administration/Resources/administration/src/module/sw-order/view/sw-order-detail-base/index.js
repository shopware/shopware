import { Component, State } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import './sw-order-detail-base.scss';
import ApiService from 'src/core/service/api.service';

import EntityStore from 'src/core/data/EntityStore';
import EntityProxy from 'src/core/data/EntityProxy';
import template from './sw-order-detail-base.html.twig';


Component.register('sw-order-detail-base', {
    template,
    inject: ['orderService', 'versionCommitService', 'userService', 'stateStyleDataProviderService'],
    props: {
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },
    data() {
        return {
            isLoading: true,
            isEditing: false,
            isDisplayingLeavePageWarning: false,
            hasAssociations: false,
            hasDeliveries: false,
            hasDeliveryTrackingCode: false,
            hasDifferentBillingAndShippingAddress: false,
            lastVersionId: null,
            addressBeingEdited: null,
            nextRoute: null,
            countries: null,
            liveVersionId: '20080911ffff4fffafffffff19830531',
            currentOrder: null,
            transactionOptions: [],
            orderOptions: []
        };
    },
    computed: {
        shippingCostsDetail() {
            const calcTaxes = this.sortByTaxRate(this.currentOrder.shippingCosts.calculatedTaxes.elements);
            const formattedTaxes = `${calcTaxes.map(
                calcTax => `${this.$tc('sw-order.detailBase.shippingCostsTax', 0, {
                    taxRate: calcTax.taxRate,
                    tax: format.currency(calcTax.tax, this.currentOrder.currency.shortName)
                })}`
            ).join('<br>')}`;

            return `${this.$tc('sw-order.detailBase.tax')}<br>${formattedTaxes}`;
        },
        sortedCalculatedTaxes() {
            return this.sortByTaxRate(this.currentOrder.price.calculatedTaxes.elements);
        },
        countryStore() {
            return State.getStore('country');
        },
        paymentMethodStore() {
            return State.getStore('payment_method');
        },
        orderAddressStore() {
            return State.getStore('order_address');
        },
        billingAddress() {
            return this.currentOrder.addresses.find((address) => {
                return address.id === this.currentOrder.billingAddressId;
            });
        },
        orderDate() {
            if (this.currentOrder && !this.currentOrder.isLoading) {
                return format.date(this.currentOrder.date);
            }
            return '';
        },
        lastChangedDate() {
            if (this.currentOrder) {
                if (this.currentOrder.updatedAt) {
                    return format.date(this.currentOrder.updatedAt);
                }
                return this.orderDate;
            }
            return '';
        },
        transactionOptionPlaceholder() {
            if (this.isLoading) return null;

            return `${this.$tc('sw-order.stateCard.headlineTransactionState')}: \
            ${this.currentOrder.transactions[0].stateMachineState.meta.viewData.name}`;
        },
        transactionOptionsBackground() {
            if (this.isLoading) return null;

            return this.stateStyleDataProviderService.getStyle('order_transaction.state',
                this.currentOrder.transactions[0].stateMachineState.technicalName).selectBackgroundStyle;
        },
        orderOptionPlaceholder() {
            if (this.isLoading) return null;

            return `${this.$tc('sw-order.stateCard.headlineOrderState')}: \
            ${this.currentOrder.stateMachineState.meta.viewData.name}`;
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

            this.countryStore.getList({ page: 1, limit: 100, sortBy: 'name' }).then((response) => {
                this.countries = response.items;
            });

            this.recalculationOrderStore = new EntityStore(this.order.getEntityName(),
                this.orderService,
                EntityProxy);

            this.recalculationOrderStore.add(this.order);
            this.currentOrder = this.order;
            this.reloadOrderAssociations();
        },
        sortByTaxRate(price) {
            return price.sort((prev, current) => {
                return prev.taxRate - current.taxRate;
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
        onLineItemChanges() {
            this.isLoading = true;

            this.orderService.recalculateOrder(this.currentOrder.id, this.currentOrder.versionId, {}, {}).then(() => {
                this.reloadVersionedOrder(this.currentOrder.versionId);
            }).catch((error) => {
                this.$emit('sw-order-detail-base-error', error);
            });
        },
        onRecalculateOrder() {
            this.onLineItemChanges();
        },
        loadLiveVersion() {
            this.reloadVersionedOrder(this.liveVersionId);
        },
        createVersionedOrder() {
            this.isLoading = true;

            this.orderService.versionize(this.order.id).then((response) => {
                const tmpVersionId = response.data.version_id;
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

            const delivieries = this.currentOrder.getAssociation('deliveries').getList(
                { page: 1, limit: 50, versionId: this.currentOrder.versionId }
            );

            const transactions = this.currentOrder.getAssociation('transactions').getList(
                { page: 1, limit: 50, versionId: this.currentOrder.versionId }
            );

            return Promise.all([addresses, delivieries, transactions]).then(() => {
                this.hasDeliveries = this.currentOrder &&
                    this.currentOrder.deliveries &&
                    this.currentOrder.deliveries.length > 0;

                this.hasDeliveryTrackingCode = this.hasDeliveries && this.currentOrder.deliveries[0].trackingCode;

                this.hasDifferentBillingAndShippingAddress = this.hasDeliveries &&
                    this.billingAddress.id !== this.currentOrder.deliveries[0].shippingOrderAddress.id;

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
        changeLanguage() {
            this.reloadVersionedOrder(this.currentOrder.versionId);
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
        onAddressModalClose() {
            this.addressBeingEdited = null;
        },
        onAddressModalSave() {
            this.addressBeingEdited = null;
            this.saveAndReloadVersionedOrder();
        },
        onAddressModalAddressSelected(address) {
            const editedId = this.addressBeingEdited.id;
            this.addressBeingEdited = null;

            this.$nextTick(() => {
                return this.orderService.changeOrderAddress(
                    editedId,
                    address.id,
                    {},
                    ApiService.getVersionHeader(this.currentOrder.versionId)
                ).then(() => {
                    return this.saveAndReloadVersionedOrder();
                }).catch((error) => {
                    this.$emit('sw-order-detail-base-error', error);
                });
            });
        },
        onEditBillingAddress() {
            if (this.isEditing) {
                this.addressBeingEdited = this.billingAddress;
            }
        },
        onEditDeliveryAddress() {
            if (this.isEditing) {
                this.addressBeingEdited = this.currentOrder.deliveries[0].shippingOrderAddress;
            }
        },
        onAddNewDeliveryAddress() {
            if (!this.isEditing) {
                return;
            }

            this.orderAddressStore.getByIdAsync(
                this.currentOrder.deliveries[0].shippingOrderAddress.id,
                '',
                this.currentOrder.versionId
            )
                .then(() => {
                    const tmp = this.orderAddressStore.duplicate(this.currentOrder.deliveries[0].shippingOrderAddress.id);
                    this.currentOrder.deliveries[0].shippingOrderAddressId = tmp.id;
                    return tmp.save();
                })
                .then(() => {
                    return this.saveAndReloadVersionedOrder();
                })
                .then(() => {
                    return this.$nextTick(() => {
                        this.onEditDeliveryAddress();
                    });
                })
                .catch((error) => {
                    this.$emit('sw-order-detail-base-error', error);
                });
        },
        onCustomerEmailEdited(email) {
            this.currentOrder.orderCustomer.email = email;
            this.saveAndReloadVersionedOrder();
        },
        onShippingChargeEdited(amount) {
            this.currentOrder.deliveries[0].shippingCosts.unitPrice = amount;
            this.currentOrder.deliveries[0].shippingCosts.totalPrice = amount;
            return this.currentOrder.save().then(() => {
                this.onLineItemChanges();
            });
        },
        onCustomerPhoneNumberEdited(number) {
            this.billingAddress.phoneNumber = number;
            this.saveAndReloadVersionedOrder();
        }
    }
});
