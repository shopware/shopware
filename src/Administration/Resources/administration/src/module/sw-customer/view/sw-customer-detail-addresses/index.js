import { Component, Mixin, Entity } from 'src/core/shopware';
import { object } from 'src/core/service/util.service';
import { required } from 'src/core/service/validation.service';
import template from './sw-customer-detail-addresses.html.twig';
import './sw-customer-detail-addresses.scss';

Component.register('sw-customer-detail-addresses', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    inject: ['customerAddressService'],

    props: {
        customer: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            isLoading: false,
            offset: 0,
            limit: 10,
            paginationSteps: [10, 25, 50, 75, 100],
            showAddAddressModal: false,
            showEditAddressModal: false,
            showDeleteAddressModal: false,
            currentAddress: null,
            addresses: []
        };
    },

    computed: {
        customerAddressStore() {
            return this.customer.getAssociation('addresses');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            params.limit = 10;

            this.customerAddressStore.getList(params).then((response) => {
                this.total = response.total;
                this.addresses = response.items;
            }).finally(() => {
                this.isLoading = false;

                if (this.$route.query.detailId) {
                    this.onEditAddress(this.$route.query.detailId);
                }
            });
        },

        onCreateNewAddress() {
            this.showAddAddressModal = true;
            this.createNewCustomerAddress();
        },

        createNewCustomerAddress() {
            const newAddress = this.createEmptyAddress();

            newAddress.customerId = this.customer.id;

            this.currentAddress = newAddress;
        },

        onSaveAddress() {
            if (this.currentAddress === null) {
                return;
            }

            if (!this.isValidAddress(this.currentAddress)) {
                this.createNotificationError({
                    title: this.$tc('global.notification.notificationSaveErrorTitle'),
                    message: this.$tc('sw-customer.notification.requiredFields')
                });
                return;
            }

            const address = this.customer.addresses.find(a => a.id === this.currentAddress.id);

            if (typeof address === 'undefined') {
                this.customer.addresses.push(this.currentAddress);
                this.addresses.push(this.currentAddress);
            } else {
                Object.assign(address, this.currentAddress);
            }

            this.currentAddress = null;
        },

        isValidAddress(address) {
            const requiredAddressFields = Entity.getRequiredProperties('customer_address');
            let isValid = true;

            isValid = requiredAddressFields.every((field) => {
                return required(address[field]);
            });

            return isValid;
        },

        onCloseAddressModal() {
            if (this.hasOwnProperty('defaultShippingAddressId')) {
                this.customer.defaultShippingAddressId = this.defaultShippingAddressId;
            }

            if (this.hasOwnProperty('defaultBillingAddressId')) {
                this.customer.defaultBillingAddressId = this.defaultBillingAddressId;
            }

            this.currentAddress = null;
        },

        createEmptyAddress() {
            return this.customerAddressStore.create();
        },

        onEditAddress(id) {
            this.currentAddress = object.deepCopyObject(this.customer.addresses.find(a => a.id === id));
            this.showEditAddressModal = id;
        },

        onDeleteAddress(id) {
            if (this.isDefaultAddress(id)) {
                return;
            }
            this.showDeleteAddressModal = id;
        },

        onConfirmDeleteAddress(id) {
            this.$nextTick(() => {
                this.customerAddressStore.getById(id).delete();
                this.customer.addresses = this.customer.addresses.filter(a => a.id !== id);
                this.customer.save().then(() => {
                    this.getList();
                    this.onCloseDeleteAddressModal();
                });
            });
        },

        onCloseDeleteAddressModal() {
            this.showDeleteAddressModal = false;
        },

        isDefaultAddress(addressId) {
            return this.customer.defaultBillingAddressId === addressId ||
                this.customer.defaultShippingAddressId === addressId;
        },

        onChangeDefaultBillingAddress(billingAddressId) {
            this.customer.defaultBillingAddressId = billingAddressId;
        },

        onChangeDefaultShippingAddress(shippingAddressId) {
            this.customer.defaultShippingAddressId = shippingAddressId;
        },

        onDuplicateAddress(addressId) {
            this.customerAddressService.clone(addressId).then(() => {
                this.getList();
            });
        },

        onChangeDefaultAddress(data) {
            if (!data.value) {
                return;
            }

            const preFix = this.createPrefix(data.name, '-address');
            const name = `default${preFix}AddressId`;

            this[name] = this.customer[name];
            this.customer[name] = data.id;
        },

        onChange(term) {
            this.term = term;
            this.getList();
        },

        createPrefix(string, replace) {
            const preFix = string.replace(replace, '');

            return `${preFix.charAt(0).toUpperCase()}${preFix.slice(1)}`;
        },

        onPageChange(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.getList();
        }
    }
});
