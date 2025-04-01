import template from './sw-order-detail-details.html.twig';
import './sw-order-detail-details.scss';

/**
 * @sw-package checkout
 */

const { Component, Store } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
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

        isSaveSuccessful: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            customFieldSets: [],
            showStateHistoryModal: false,
        };
    },

    computed: {
        isLoading: () => Store.get('swOrderDetail').isLoading,

        order: () => Store.get('swOrderDetail').order,

        versionContext: () => Store.get('swOrderDetail').versionContext,

        orderAddressIds: () => Store.get('swOrderDetail').orderAddressIds,

        ...mapPropertyErrors('order', ['orderCustomer.email']),

        delivery() {
            return this.order.deliveries.length > 0 && this.order.deliveries[0];
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
            return this.order.transactions.last();
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

        onShippingChargeEdited(amount) {
            this.delivery.shippingCosts.unitPrice = amount;
            this.delivery.shippingCosts.totalPrice = amount;

            this.saveAndRecalculate();
        },

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

        updateLoading(loadingValue) {
            Store.get('swOrderDetail').setLoading(['order', loadingValue]);
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
