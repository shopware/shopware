import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';
import swOrderDetailState from '../../state/order-detail.store';

/**
 * @package checkout
 */

const { State, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();
const ApiService = Shopware.Classes.ApiService;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'orderService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        orderId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            identifier: '',
            isEditing: false,
            isLoading: true,
            isSaveSuccessful: false,
            createdById: '',
            isDisplayingLeavePageWarning: false,
            nextRoute: null,
            hasNewVersionId: false,
            hasOrderDeepEdit: false,
            missingProductLineItems: [],
            promotionsToDelete: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
            'orderAddressIds',
            'editing',
        ]),

        orderIdentifier() {
            return this.order !== null ? this.order.orderNumber : '';
        },

        orderChanges() {
            if (!this.order) {
                return false;
            }

            return this.orderRepository.hasChanges(this.order);
        },

        showTabs() {
            return this.$route.meta.$module.routes.detail.children.length > 1;
        },

        showWarningTabStyle() {
            return this.isOrderEditing && this.$route.name === 'sw.order.detail.documents';
        },

        isOrderEditing() {
            return this.orderChanges || this.hasOrderDeepEdit || this.orderAddressIds?.length > 0;
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        automaticPromotions() {
            return this.order.lineItems.filter(item => item.type === 'promotion' && item.referencedId === null);
        },

        orderCriteria() {
            const criteria = new Criteria(1, 25);

            criteria
                .addAssociation('currency')
                .addAssociation('orderCustomer.salutation')
                .addAssociation('language');

            criteria
                .getAssociation('lineItems')
                .addFilter(Criteria.equals('parentId', null))
                .addSorting(Criteria.sort('position', 'ASC'));

            criteria
                .getAssociation('lineItems.children')
                .addSorting(Criteria.naturalSorting('label'));

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

            criteria.addAssociation('stateMachineState');

            criteria
                .getAssociation('deliveries')
                .addAssociation('stateMachineState')
                .addSorting(Criteria.sort('shippingCosts.unitPrice', 'DESC'));

            criteria.getAssociation('transactions')
                .addAssociation('stateMachineState')
                .addSorting(Criteria.sort('createdAt'));

            criteria.addAssociation('billingAddress');

            return criteria;
        },
    },

    watch: {
        orderId() {
            this.createdComponent();
        },

        isOrderEditing(value) {
            this.updateEditing(value);
        },
    },

    beforeCreate() {
        State.registerModule('swOrderDetail', swOrderDetailState);
    },

    beforeDestroy() {
        this.beforeDestroyComponent();

        State.unregisterModule('swOrderDetail');
    },

    beforeRouteLeave(to, from, next) {
        if (this.isOrderEditing) {
            this.nextRoute = next;
            this.isDisplayingLeavePageWarning = true;
        } else {
            next();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-order-detail-base__order',
                path: 'order',
                scope: this,
            });

            window.addEventListener('beforeunload', this.beforeDestroyComponent);

            Shopware.State.commit(
                'shopwareApps/setSelectedIds',
                this.orderId ? [this.orderId] : [],
            );

            State.commit('swOrderDetail/setVersionContext', Shopware.Context.api);
            this.createNewVersionId();
        },

        async beforeDestroyComponent() {
            if (this.hasNewVersionId) {
                const oldVersionContext = this.versionContext;
                State.commit('swOrderDetail/setVersionContext', Shopware.Context.api);

                // clean up recently created version
                await this.orderRepository.deleteVersion(
                    this.orderId,
                    oldVersionContext.versionId,
                    oldVersionContext,
                );
            }
        },

        updateIdentifier(identifier) {
            this.identifier = identifier;
        },

        updateCreatedById(createdById) {
            this.createdById = createdById;
        },

        onChangeLanguage() {
            this.$root.$emit('language-change');
        },

        saveEditsFinish() {
            this.isSaveSuccessful = false;
            this.isEditing = false;
        },

        onStartEditing() {
            this.$root.$emit('order-edit-start');
        },

        async onSaveEdits() {
            this.isLoading = true;

            await this.handleOrderAddressUpdate(this.orderAddressIds);

            if (this.promotionsToDelete.length > 0) {
                this.order.lineItems = this.order.lineItems.filter(
                    (lineItem) => !this.promotionsToDelete.includes(lineItem.id),
                );
            }

            this.orderRepository.save(this.order, this.versionContext)
                .then(() => {
                    this.hasOrderDeepEdit = false;
                    this.promotionsToDelete = [];
                    return this.orderRepository.mergeVersion(this.versionContext.versionId, this.versionContext);
                }).catch((error) => {
                    this.onError('error', error);
                }).finally(() => {
                    State.commit('swOrderDetail/setVersionContext', Shopware.Context.api);

                    return this.createNewVersionId().then(() => {
                        State.commit('swOrderDetail/setLoading', ['order', false]);
                        State.commit('swOrderDetail/setSavedSuccessful', true);
                        this.isLoading = false;
                    });
                });

            this.$root.$emit('order-edit-save');
        },

        async handleOrderAddressUpdate(addressMappings) {
            const mappings = [];

            addressMappings.forEach((addressMapping) => {
                // If they are the same means that the address has not changed, so skip it
                if (addressMapping.customerAddressId === addressMapping.orderAddressId) {
                    return;
                }

                const mapping = {
                    customerAddressId: addressMapping.customerAddressId,
                    type: addressMapping.type,
                };

                if (addressMapping.type === 'shipping') {
                    mapping.deliveryId = this.order.deliveries[0].id;
                }

                mappings.push(mapping);
            });

            if (mappings.length === 0) {
                State.commit('swOrderDetail/setOrderAddressIds', false);

                return;
            }

            await this.updateOrderAddresses(mappings)
                .then(() => {
                    State.commit('swOrderDetail/setOrderAddressIds', false);
                }).catch((error) => {
                    this.createNotificationError({
                        message: error,
                    });
                });
        },

        onCancelEditing() {
            this.isLoading = true;
            State.commit('swOrderDetail/setLoading', ['order', true]);

            const oldVersionContext = this.versionContext;
            State.commit('swOrderDetail/setVersionContext', Shopware.Context.api);
            this.hasNewVersionId = false;

            return this.orderRepository.deleteVersion(
                this.orderId,
                oldVersionContext.versionId,
                oldVersionContext,
            ).then(() => {
                this.hasOrderDeepEdit = false;
                State.commit('swOrderDetail/setOrderAddressIds', false);
            }).catch((error) => {
                this.onError('error', error);
            }).finally(() => {
                this.missingProductLineItems = [];
                this.convertedProductLineItems = [];

                return this.createNewVersionId().then(() => {
                    State.commit('swOrderDetail/setLoading', ['order', false]);
                });
            });
        },

        async onSaveAndRecalculate() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.isLoading = true;

            this.order.lineItems = this.order.lineItems.filter((lineItem) => !this.automaticPromotions.includes(lineItem));
            try {
                await this.orderRepository.save(this.order, this.versionContext);
                await this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {});
                await this.orderService.toggleAutomaticPromotions(this.orderId, this.versionContext.versionId, false);
                await this.reloadEntityData();
            } catch (error) {
                this.onError('error', error);
            } finally {
                this.isLoading = false;
                Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);
            }
        },

        async onRecalculateAndReload() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            try {
                this.promotionsToDelete = this.automaticPromotions.map(promotion => promotion.id);
                await this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {});
                await this.orderService.toggleAutomaticPromotions(this.orderId, this.versionContext.versionId, false);
                await this.reloadEntityData();
                this.order.lineItems = this.order.lineItems.filter(
                    (lineItem) => !this.promotionsToDelete.includes(lineItem.id),
                );
            } catch (error) {
                this.onError('error', error);
                this.promotionsToDelete = [];
            } finally {
                Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);
            }
        },

        onSaveAndReload() {
            State.commit('swOrderDetail/setLoading', ['order', true]);

            return this.orderRepository.save(this.order, this.versionContext).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.onError('error', error);
            }).finally(() => {
                Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);

                return Promise.resolve();
            });
        },

        onUpdateLoading(loadingValue) {
            this.isLoading = loadingValue;
        },

        onUpdateEditing(editingValue) {
            this.isEditing = editingValue;
        },

        onError(error) {
            let errorDetails = null;

            try {
                errorDetails = error.response.data.errors[0].detail;
            } catch (e) {
                errorDetails = '';
            }

            this.createNotificationError({
                message: this.$tc('sw-order.detail.messageRecalculationError') + errorDetails,
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

        reloadEntityData() {
            State.commit('swOrderDetail/setLoading', ['order', true]);

            return this.orderRepository.get(this.orderId, this.versionContext, this.orderCriteria).then((response) => {
                if (this.$route.name !== 'sw.order.detail.documents') {
                    this.hasOrderDeepEdit = true;
                }

                State.commit('swOrderDetail/setOrder', response);
                State.commit('swOrderDetail/setLoading', ['order', false]);
                this.isLoading = false;

                return Promise.resolve();
            }).catch(() => {
                Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);

                return Promise.reject();
            });
        },

        createNewVersionId() {
            return this.orderRepository.createVersion(this.orderId, this.versionContext).then((newContext) => {
                this.hasNewVersionId = true;

                State.commit('swOrderDetail/setVersionContext', newContext);

                this.orderRepository.get(this.orderId, newContext, this.orderCriteria).then((response) => {
                    State.commit('swOrderDetail/setOrder', response);
                    State.commit('swOrderDetail/setLoading', ['order', false]);
                    this.isLoading = false;

                    return Promise.resolve();
                }).catch(() => {
                    Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);

                    return Promise.reject();
                });
            });
        },

        updateOrderAddresses(mappings) {
            return this.orderService.updateOrderAddresses(
                this.orderId,
                mappings,
                {},
                ApiService.getVersionHeader(this.order.versionId),
            );
        },

        updateEditing(value) {
            State.commit('swOrderDetail/setEditing', value);
        },
    },
};
