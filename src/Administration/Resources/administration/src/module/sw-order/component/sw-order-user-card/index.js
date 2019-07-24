import { Component, Mixin } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import ApiService from 'src/core/service/api.service';
import Criteria from 'src/core/data-new/criteria.data';
import './sw-order-user-card.scss';
import template from './sw-order-user-card.html.twig';


Component.register('sw-order-user-card', {
    template,

    inject: [
        'orderService',
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('salutation')
    ],

    props: {
        currentOrder: {
            type: Object,
            required: true
        },
        versionContext: {
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
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        orderAddressRepository() {
            return this.repositoryFactory.create('order_address');
        },

        billingAddress() {
            return this.currentOrder.addresses.find((address) => {
                return address.id === this.currentOrder.billingAddressId;
            });
        },
        delivery() {
            return this.currentOrder.deliveries[0];
        },

        orderDate() {
            if (this.currentOrder && !this.currentOrder.isLoading) {
                return format.date(this.currentOrder.orderDateTime);
            }
            return '';
        },
        hasDeliveries() {
            return this.currentOrder.deliveries.length > 0;
        },
        hasDeliveryTrackingCode() {
            return this.hasDeliveries && this.delivery.trackingCode;
        },
        hasDifferentBillingAndShippingAddress() {
            return this.hasDeliveries &&
                this.billingAddress.id !== this.delivery.shippingOrderAddress.id;
        },
        lastChangedDate() {
            if (this.currentOrder) {
                if (this.currentOrder.updatedAt) {
                    return format.date(
                        this.currentOrder.updatedAt
                    );
                }

                return format.date(
                    this.currentOrder.orderDateTime
                );
            }
            return '';
        },

        hasTags() {
            return this.currentOrder.tags.length !== 0;
        },

        fullName() {
            const name = {
                name: this.salutation(this.currentOrder.orderCustomer),
                company: this.currentOrder.orderCustomer.company
            };

            return Object.values(name).filter(item => item !== null).join(' - ').trim();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.reload();
        },

        reload() {
            this.countryRepository.search(this.countryCriteria(), this.context).then((response) => {
                this.countries = response;
            });
        },

        countryCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        onEditBillingAddress() {
            if (this.isEditing) {
                this.addressBeingEdited = this.billingAddress;
            }
        },

        onEditDeliveryAddress() {
            if (this.isEditing) {
                this.addressBeingEdited = this.delivery.shippingOrderAddress;
            }
        },

        onAddressModalSave() {
            this.addressBeingEdited = null;
            this.$nextTick(() => {
                this.emitChange();
            });
        },

        onResetOrder() {
            this.addressBeingEdited = null;
            this.$nextTick(() => {
                this.$emit('order-reset');
            });
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
                    this.emitChange();
                }).catch((error) => {
                    this.$emit('error', error);
                });
            });
        },

        onAddNewDeliveryAddress() {
            if (!this.isEditing) {
                return;
            }

            this.orderAddressRepository.clone(
                this.delivery.shippingOrderAddress.id,
                this.versionContext
            ).then((response) => {
                this.delivery.shippingOrderAddressId = response.id;
                this.emitChange();
            }).catch((error) => {
                this.$emit('error', error);
            });
        },
        emitChange() {
            this.$emit('order-change');
        }
    }

});
