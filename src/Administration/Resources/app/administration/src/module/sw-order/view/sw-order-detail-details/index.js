import template from './sw-order-detail-details.html.twig';
import './sw-order-detail-details.scss';

/**
 * @package customer-order
 */

const { Component, State } = Shopware;
const { Criteria } = Shopware.Data;
const { mapGetters, mapState } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
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
        ...mapGetters('swOrderDetail', [
            'isLoading',
        ]),

        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
            'orderAddressIds',
        ]),

        delivery() {
            return this.order.deliveries.length > 0 && this.order.deliveries[0];
        },

        transaction() {
            for (let i = 0; i < this.order.transactions.length; i += 1) {
                if (!['cancelled', 'failed'].includes(this.order.transactions[i].stateMachineState.technicalName)) {
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
            const criteria = new Criteria(1, 25);

            if (this.order.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.order.salesChannelId));
            }

            return criteria;
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
            const currentAddress = this.orderAddressIds.find(item => item.type === 'billing');
            return currentAddress?.customerAddressId || this.billingAddress.id;
        },

        selectedShippingAddressId() {
            const currentAddress = this.orderAddressIds.find(item => item.type === 'shipping');
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
            this.$emit('update-loading', true);

            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((result) => {
                this.customFieldSets = result;
                this.$emit('update-loading', false);
            });
        },

        onShippingChargeEdited(amount) {
            this.delivery.shippingCosts.unitPrice = amount;
            this.delivery.shippingCosts.totalPrice = amount;

            this.saveAndRecalculate();
        },

        saveAndRecalculate() {
            this.$emit('save-and-recalculate');
        },

        saveAndReload() {
            this.$emit('save-and-reload');
        },

        onSaveEdits() {
            this.$emit('save-edits');
        },

        reloadEntityData() {
            this.$emit('reload-entity-data');
        },

        showError(error) {
            this.$emit('error', error);
        },

        updateLoading(loadingValue) {
            State.commit('swOrderDetail/setLoading', ['order', loadingValue]);
        },

        validateTrackingCode(searchTerm) {
            const trackingCode = searchTerm.trim();

            if (trackingCode.length <= 0) {
                return false;
            }

            const isExist = this.delivery?.trackingCodes?.find(code => code === trackingCode);
            return !isExist;
        },

        onChangeOrderAddress(value) {
            State.commit('swOrderDetail/setOrderAddressIds', value);
        },
    },
};
