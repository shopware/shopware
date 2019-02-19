import { Component, Mixin, Entity } from 'src/core/shopware';
import { object } from 'src/core/service/util.service';
import { required } from 'src/core/service/validation.service';
import template from './sw-customer-detail-addresses.html.twig';
import './sw-customer-detail-addresses.scss';

Component.register('sw-customer-detail-addresses', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    inject: ['customerAddressService'],

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
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
            showAddAddressModal: false,
            showEditAddressModal: false,
            showDeleteAddressModal: false,
            currentAddress: null,
            addresses: [],
            isLoading: false
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

            this.customerAddressStore.getList(params).then((response) => {
                this.addresses = response.items;
            }).finally(() => {
                this.isLoading = false;
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
                return;
            }

            const address = this.customer.addresses.find(a => a.id === this.currentAddress.id);

            if (typeof address === 'undefined') {
                this.customer.addresses.push(this.currentAddress);
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
            this.customer.save();
        },

        onChangeDefaultShippingAddress(shippingAddressId) {
            this.customer.defaultShippingAddressId = shippingAddressId;
            this.customer.save();
        },

        onDuplicateAddress(addressId) {
            this.customerAddressService.clone(addressId).then(() => {
                this.getList();
            });
        },

        onChange(term) {
            this.term = term;
            this.getList();
        }
    }
});
