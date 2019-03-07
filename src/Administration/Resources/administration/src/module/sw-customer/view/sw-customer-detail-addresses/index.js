import { Component, State, Mixin, Entity } from 'src/core/shopware';
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
        },
        customerEditMode: {
            type: Boolean,
            required: true
        },
        customerAddressAttributeSets: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            activeCustomer: this.customer,
            disableRouteParams: true,
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
        customerStore() {
            return State.getStore('customer');
        },
        customerAddressStore() {
            return this.activeCustomer.getAssociation('addresses');
        }
    },

    methods: {
        getList() {
            if (!this.activeCustomer.id && this.$route.params.id) {
                this.activeCustomer = this.customerStore.getById(this.$route.params.id);
            }
            if (!this.activeCustomer.id) {
                this.$router.push({ name: 'sw.customer.detail.base', params: { id: this.$route.params.id } });
                return;
            }
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

            newAddress.customerId = this.activeCustomer.id;

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

            const address = this.activeCustomer.addresses.find(a => a.id === this.currentAddress.id);

            if (typeof address === 'undefined') {
                this.activeCustomer.addresses.push(this.currentAddress);
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
                this.activeCustomer.defaultShippingAddressId = this.defaultShippingAddressId;
            }

            if (this.hasOwnProperty('defaultBillingAddressId')) {
                this.activeCustomer.defaultBillingAddressId = this.defaultBillingAddressId;
            }

            if (this.$route.query.hasOwnProperty('detailId')) {
                this.$route.query.detailId = null;
            }

            this.currentAddress = null;
        },

        createEmptyAddress() {
            return this.customerAddressStore.create();
        },

        onEditAddress(id) {
            this.currentAddress = object.deepCopyObject(this.activeCustomer.addresses.find(a => a.id === id));
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
                this.activeCustomer.addresses = this.activeCustomer.addresses.filter(a => a.id !== id);
                this.activeCustomer.save().then(() => {
                    this.getList();
                    this.onCloseDeleteAddressModal();
                });
            });
        },

        onCloseDeleteAddressModal() {
            this.showDeleteAddressModal = false;
        },

        isDefaultAddress(addressId) {
            return this.activeCustomer.defaultBillingAddressId === addressId ||
                this.activeCustomer.defaultShippingAddressId === addressId;
        },

        onChangeDefaultBillingAddress(billingAddressId) {
            this.activeCustomer.defaultBillingAddressId = billingAddressId;
        },

        onChangeDefaultShippingAddress(shippingAddressId) {
            this.activeCustomer.defaultShippingAddressId = shippingAddressId;
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

            this[name] = this.activeCustomer[name];
            this.activeCustomer[name] = data.id;
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
