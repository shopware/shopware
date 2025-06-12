import template from './sw-order-detail-details.html.twig';
import './sw-order-detail-details.scss';

/**
 * @sw-package checkout
 */

const { Component, Store, Utils } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        feature: {
            from: 'feature',
            default: null,
        },
        swOrderDetailOnSaveAndReload: {
            from: 'swOrderDetailOnSaveAndReload',
            default: null,
        },
        swOrderDetailOnSaveEdits: {
            from: 'swOrderDetailOnSaveEdits',
            default: null,
        },
        swOrderDetailOnLoadingChange: {
            from: 'swOrderDetailOnLoadingChange',
            default: null,
        },
        swOrderDetailOnSaveAndRecalculate: {
            from: 'swOrderDetailOnSaveAndRecalculate',
            default: null,
        },
        swOrderDetailOnReloadEntityData: {
            from: 'swOrderDetailOnReloadEntityData',
            default: null,
        },
        swOrderDetailOnError: {
            from: 'swOrderDetailOnError',
            default: null,
        },
        acl: {
            from: 'acl',
            default: null,
        },
        repositoryFactory: {
            from: 'repositoryFactory',
            default: null,
        },
    },

    emits: [
        'update-loading',
        'save-and-recalculate',
        'save-and-reload',
        'save-edits',
        'reload-entity-data',
        'error',
    ],

    props: {
        orderId: {
            type: String,
            required: true,
        },

        /** @deprecated tag:v6.8.0 - will be removed without replacement */
        isSaveSuccessful: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            customFieldSets: [],
            showStateHistoryModal: false,
        };
    },

    computed: {
        /** @deprecated tag:v6.8.0 - will be removed, use loading.order instead */
        isLoading: () => Store.get('swOrderDetail').isLoading,

        order: () => Store.get('swOrderDetail').order,

        versionContext: () => Store.get('swOrderDetail').versionContext,

        orderAddressIds: () => Store.get('swOrderDetail').orderAddressIds,

        ...mapPropertyErrors('order', ['orderCustomer.email']),

        delivery() {
            if (!Shopware.Feature.isActive('v6.8.0.0')) {
                return this.order.deliveries.length > 0 && this.order.deliveries[0];
            }

            return this.order.primaryOrderDelivery;
        },

        transaction() {
            for (let i = 0; i < this.order.transactions.length; i += 1) {
                if (
                    ![
                        'cancelled',
                        'failed',
                    ].includes(this.order.transactions[i].stateMachineState.technicalName)
                ) {
                    return this.order.transactions[i];
                }
            }

            if (!Shopware.Feature.isActive('v6.8.0.0')) {
                return this.order.transactions.last();
            }

            return this.order.primaryOrderTransaction;
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
            return new Criteria(1, 25);
        },

        taxStatus() {
            return this.order.price.taxStatus;
        },

        // @deprecated tag:v6.8.0 - Will be removed, will not be used anymore.
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
            const currentAddress = this.orderAddressIds.find((item) => item.type === 'billing');
            return currentAddress?.customerAddressId || this.billingAddress.id;
        },

        selectedShippingAddressId() {
            const currentAddress = this.orderAddressIds.find((item) => item.type === 'shipping');
            return currentAddress?.customerAddressId || this.shippingAddress.id;
        },

        // @deprecated tag:v6.8.0 - Will be removed, change shipping cost on order general view instead.
        shippingCosts: {
            get() {
                return this.delivery?.shippingCosts.totalPrice || 0.0;
            },
            set(value) {
                this.onShippingChargeEdited(value);
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadingChange(true);

            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((result) => {
                this.customFieldSets = result;
                this.loadingChange(false);
            });
        },

        // @deprecated tag:v6.8.0 - Will be removed, change shipping cost on order general view instead.
        onShippingChargeEdited: Utils.debounce(function onShippingChargeEdited(amount) {
            if (amount >= 0) {
                this.delivery.shippingCosts.unitPrice = amount;
                this.delivery.shippingCosts.totalPrice = amount;
            }

            this.saveAndRecalculate();
        }, 800),

        loadingChange(loading) {
            if (this.swOrderDetailOnLoadingChange) {
                this.swOrderDetailOnLoadingChange(loading);
            } else {
                this.$emit('update-loading', loading);
            }
        },

        saveAndRecalculate() {
            if (this.swOrderDetailOnSaveAndRecalculate) {
                this.swOrderDetailOnSaveAndRecalculate();
            } else {
                this.$emit('save-and-recalculate');
            }
        },

        saveAndReload() {
            if (this.swOrderDetailOnSaveAndReload) {
                this.swOrderDetailOnSaveAndReload();
            } else {
                this.$emit('save-and-reload');
            }
        },

        onSaveEdits() {
            if (this.swOrderDetailOnSaveEdits) {
                this.swOrderDetailOnSaveEdits();
            } else {
                this.$emit('save-edits');
            }
        },

        reloadEntityData() {
            if (this.swOrderDetailOnReloadEntityData) {
                this.swOrderDetailOnReloadEntityData();
            } else {
                this.$emit('reload-entity-data');
            }
        },

        showError(error) {
            if (this.swOrderDetailOnError) {
                this.swOrderDetailOnError(error);
            } else {
                this.$emit('error', error);
            }
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        updateLoading(loadingValue) {
            Store.get('swOrderDetail').setLoading([
                'order',
                loadingValue,
            ]);
        },

        validateTrackingCode(searchTerm) {
            const trackingCode = searchTerm.trim();

            if (trackingCode.length <= 0) {
                return false;
            }

            const isExist = this.delivery?.trackingCodes?.find((code) => code === trackingCode);
            return !isExist;
        },

        onChangeOrderAddress(value) {
            Store.get('swOrderDetail').setOrderAddressIds(value);
        },
    },
};
