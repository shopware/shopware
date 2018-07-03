import { Component, Entity } from 'src/core/shopware';
import { object } from 'src/core/service/util.service';
import { required } from 'src/core/service/validation.service';
import template from './sw-customer-detail-addresses.html.twig';
import './sw-customer-detail-addresses.less';

Component.register('sw-customer-detail-addresses', {
    template,

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
        },
        addresses: {
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
            currentAddress: null
        };
    },

    computed: {
        customerAddressStore() {
            return this.customer.getAssociationStore('addresses');
        }
    },

    methods: {
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
            this.customerAddressStore.getById(id).delete();
            this.customer.addresses = this.customer.addresses.filter(a => a.id !== id);
        },

        onCloseDeleteAddressModal() {
            this.showDeleteAddressModal = false;
        },

        isDefaultAddress(addressId) {
            return this.customer.defaultBillingAddressId === addressId ||
                this.customer.defaultShippingAddressId === addressId;
        }
    }
});
