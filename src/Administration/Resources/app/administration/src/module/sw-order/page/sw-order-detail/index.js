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
                .addSorting(Criteria.sort('position', 'ASC'));

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

        convertedProductLineItems() {
            return this.order?.lineItems?.filter((lineItem) => {
                return lineItem.payload?.isConvertedProductLineItem
                    && lineItem.type === 'custom'
                    && !this.missingProductLineItems.includes(lineItem);
            }) || [];
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

            // change new order address
            if (this.orderAddressIds?.length) {
                await Promise.all([
                    ...this.orderAddressIds
                        .filter(ids => ids.orderAddressId !== ids.customerAddressId)
                        .map(ids => this.changeOrderAddress(ids)),
                ]).then(() => {
                    State.commit('swOrderDetail/setOrderAddressIds', false);
                }).catch((error) => {
                    this.createNotificationError({
                        message: error,
                    });
                });
            }

            this.orderRepository.save(this.order, this.versionContext)
                .then(() => {
                    this.hasOrderDeepEdit = false;
                    return this.orderRepository.mergeVersion(this.versionContext.versionId, this.versionContext);
                }).catch((error) => {
                    this.onError('error', error);
                }).finally(() => {
                    State.commit('swOrderDetail/setVersionContext', Shopware.Context.api);

                    return this.createNewVersionId().then(() => {
                        State.commit('swOrderDetail/setSavedSuccessful', true);
                    });
                });

            this.$root.$emit('order-edit-save');
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

                return this.createNewVersionId().then(() => {
                    State.commit('swOrderDetail/setLoading', ['order', false]);
                });
            });
        },

        onSaveAndRecalculate() {
            State.commit('swOrderDetail/setLoading', ['order', true]);

            return this.orderRepository.save(this.order, this.versionContext).then(() => {
                return this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {});
            }).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.onError('error', error);
            })
                .finally(() => {
                    Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);

                    return Promise.resolve();
                });
        },

        onRecalculateAndReload() {
            State.commit('swOrderDetail/setLoading', ['order', true]);

            return this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {}).then(() => {
                return this.reloadEntityData();
            }).catch((error) => {
                this.onError('error', error);
            }).finally(() => {
                Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);

                return Promise.resolve();
            });
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

        reloadEntityData(isSaved = true) {
            State.commit('swOrderDetail/setLoading', ['order', true]);

            return this.orderRepository.get(this.orderId, this.versionContext, this.orderCriteria).then((response) => {
                if (this.$route.name !== 'sw.order.detail.documents' && isSaved) {
                    this.hasOrderDeepEdit = true;
                }

                State.commit('swOrderDetail/setOrder', response);

                return Promise.resolve();
            }).finally(() => {
                Shopware.State.commit('swOrderDetail/setLoading', ['order', false]);
                this.isLoading = false;
            });
        },

        createNewVersionId() {
            return this.orderRepository.createVersion(this.orderId, this.versionContext).then((newContext) => {
                this.hasNewVersionId = true;

                State.commit('swOrderDetail/setVersionContext', newContext);

                return this.reloadEntityData(false);
            }).then(() => {
                return this.convertMissingProductLineItems();
            });
        },

        changeOrderAddress(ids) {
            const { orderAddressId, customerAddressId } = ids;

            return this.orderService.changeOrderAddress(
                orderAddressId,
                customerAddressId,
                {},
                ApiService.getVersionHeader(this.order.versionId),
            );
        },

        updateEditing(value) {
            State.commit('swOrderDetail/setEditing', value);
        },

        convertMissingProductLineItems() {
            this.missingProductLineItems = this.order?.lineItems?.filter((lineItem) => {
                return (lineItem.productId === null && lineItem.type === 'product');
            }) || [];

            if (this.missingProductLineItems.length === 0) {
                return Promise.resolve();
            }

            this.missingProductLineItems.forEach((lineItem) => {
                lineItem.type = 'custom';
                lineItem.productId = null;
                lineItem.referencedId = null;
                lineItem.payload.isConvertedProductLineItem = true;
            });

            return this.orderRepository.save(this.order, this.versionContext);
        },
    },
};
