import { Component, State } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import './sw-order-user-card.scss';
import ApiService from 'src/core/service/api.service';
import template from './sw-order-user-card.html.twig';


Component.register('sw-order-user-card', {
    template,

    inject: ['orderService'],

    props: {
        currentOrder: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: true
        },
        isEditing: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            addressBeingEdited: null,
            countries: null
        };
    },

    computed: {
        countryStore() {
            return State.getStore('country');
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
        hasDeliveries() {
            return this.currentOrder.deliveries.length > 0;
        },
        hasDeliveryTrackingCode() {
            return this.hasDeliveries && this.currentOrder.deliveries[0].trackingCode;
        },
        hasDifferentBillingAndShippingAddress() {
            return this.hasDeliveries &&
                this.billingAddress.id !== this.currentOrder.deliveries[0].shippingOrderAddress.id;
        },
        lastChangedDate() {
            if (this.currentOrder) {
                if (this.currentOrder.updatedAt) {
                    return format.date(
                        this.currentOrder.updatedAt,
                        { year: '2-digit', hour: '2-digit', minute: '2-digit' }
                    );
                }
                return this.orderDate;
            }
            return '';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.countryStore.getList({ page: 1, limit: 100, sortBy: 'name' }).then((response) => {
                this.countries = response.items;
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

        onAddressModalClose() {
            this.addressBeingEdited = null;
        },

        onAddressModalSave() {
            this.addressBeingEdited = null;
            this.$emit('order-changed');
        },

        onAddressModalAddressSelected(address) {
            const oldAddressId = this.addressBeingEdited.id;
            this.addressBeingEdited = null;
            this.$nextTick(() => {
                return this.orderService.changeOrderAddress(
                    oldAddressId,
                    address.id,
                    {},
                    ApiService.getVersionHeader(this.currentOrder.versionId)
                ).then(() => {
                    this.$emit('order-changed');
                }).catch((error) => {
                    this.$emit('error', error);
                });
            });
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
                    const orderAddress = this.orderAddressStore.duplicate(
                        this.currentOrder.deliveries[0].shippingOrderAddress.id
                    );
                    this.currentOrder.deliveries[0].shippingOrderAddressId = orderAddress.id;
                    return orderAddress.save();
                })
                .then(() => {
                    this.$emit('order-changed');
                })
                .catch((error) => {
                    this.$emit('error', error);
                });
        }
    }

});
