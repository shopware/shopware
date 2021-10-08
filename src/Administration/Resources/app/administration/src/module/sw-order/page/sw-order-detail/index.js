import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';
import swOrderDetailState from '../../state/order-detail.store';

const { Component, State, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-order-detail', {
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
        ]),

        orderIdentifier() {
            if (Shopware.Feature.isActive('FEATURE_NEXT_7530')) {
                return this.order !== null ? this.order.orderNumber : '';
            }

            return this.identifier;
        },

        orderChanges() {
            return this.orderRepository.hasChanges(this.order);
        },

        showTabs() {
            return this.$route.meta.$module.routes.detail.children.length > 1;
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderCriteria() {
            const criteria = new Criteria();

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
    },

    watch: {
        orderId() {
            this.createdComponent();
        },
    },

    beforeCreate() {
        State.registerModule('swOrderDetail', swOrderDetailState);
    },

    beforeDestroy() {
        State.unregisterModule('swOrderDetail');
    },

    beforeRouteLeave(to, from, next) {
        if (Shopware.Feature.isActive('FEATURE_NEXT_7530') && this.orderChanges) {
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
            Shopware.State.commit(
                'shopwareApps/setSelectedIds',
                this.orderId ? [this.orderId] : [],
            );

            if (Shopware.Feature.isActive('FEATURE_NEXT_7530')) {
                State.commit('swOrderDetail/setVersionContext', Shopware.Context.api); // ?? do we need that anymore?
                this.createNewVersionId();
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

        onSaveEdits() {
            if (Shopware.Feature.isActive('FEATURE_NEXT_7530')) {
                this.isLoading = true;

                this.orderRepository.save(this.order, this.versionContext)
                    .then(() => {
                        return this.orderRepository.mergeVersion(this.versionContext.versionId, this.versionContext);
                    }).catch((error) => {
                        this.onError('error', error);
                    }).finally(() => {
                        State.commit('swOrderDetail/setVersionContext', Shopware.Context.api); // ?? do we need that anymore?

                        this.createNewVersionId().then(() => {
                            State.commit('swOrderDetail/setLoading', ['order', false]);
                            State.commit('swOrderDetail/setSavedSuccessful', true);
                            this.isLoading = false;
                        });
                    });
            }

            this.$root.$emit('order-edit-save');
        },

        onCancelEditing() {
            if (Shopware.Feature.isActive('FEATURE_NEXT_7530')) {
                State.commit('swOrderDetail/setLoading', ['order', true]);

                this.orderRepository.deleteVersion(
                    this.orderId,
                    this.versionContext.versionId,
                    this.versionContext,
                ).catch((error) => {
                    this.onError('error', error);
                }).finally(() => {
                    this.missingProductLineItems = [];
                    this.convertedProductLineItems = [];

                    State.commit('swOrderDetail/setVersionContext', Shopware.Context.api); // ?? do we need that anymore?

                    this.createNewVersionId().then(() => {
                        State.commit('swOrderDetail/setLoading', ['order', false]);
                    });
                });

                return;
            }

            this.$root.$emit('order-edit-cancel');
        },

        onSaveAndRecalculate() {
            State.commit('swOrderDetail/setLoading', ['order', true]);

            return this.orderRepository.save(this.order, this.versionContext).then(() => {
                return this.orderService.recalculateOrder(this.orderId, this.versionContext.versionId, {}, {});
            }).then(() => {
                State.commit('swOrderDetail/setVersionContext', Shopware.Context.api); // ?? do we need that anymore?

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
                State.commit('swOrderDetail/setVersionContext', Shopware.Context.api); // ?? do we need that anymore?

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
    },
});
