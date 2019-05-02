import { Component, Mixin, Entity } from 'src/core/shopware';
import { object } from 'src/core/service/util.service';
import { required } from 'src/core/service/validation.service';
import template from './sw-customer-detail-addresses.html.twig';
import './sw-customer-detail-addresses.scss';

Component.register('sw-customer-detail-addresses', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'repositoryFactory',
        'context',
        'customerAddressService'
    ],

    props: {
        customer: {
            type: Object,
            required: true
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
        customerAddressCustomFieldSets: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            activeCustomer: this.customer,
            showAddAddressModal: false,
            showEditAddressModal: false,
            showDeleteAddressModal: false,
            currentAddress: null
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        addressColumns() {
            return this.getAddressColumns();
        },

        addressRepository() {
            return this.repositoryFactory.create(
                this.activeCustomer.addresses.entity,
                this.activeCustomer.addresses.source
            );
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (!this.activeCustomer.id && this.$route.params.id) {
                this.customerRepository.get(this.$route.params.id, this.context).then((customer) => {
                    this.activeCustomer = customer;
                    this.isLoading = false;
                });
                return;
            }
            if (!this.activeCustomer.id) {
                this.$router.push({ name: 'sw.customer.detail.base', params: { id: this.$route.params.id } });
                return;
            }

            this.isLoading = false;
        },

        getAddressColumns() {
            return [{
                property: 'defaultShippingAddress',
                dataIndex: 'defaultShippingAddress',
                label: this.$tc('sw-customer.detailAddresses.columnDefaultShippingAddress'),
                align: 'center',
                iconLabel: 'default-shopping-cart'
            }, {
                property: 'defaultBillingAddress',
                dataIndex: 'defaultBillingAddress',
                label: this.$tc('sw-customer.detailAddresses.columnDefaultBillingAddress'),
                align: 'center',
                iconLabel: 'default-documentation-file'
            }, {
                property: 'lastName',
                dataIndex: 'lastName',
                label: this.$tc('sw-customer.detailAddresses.columnLastName')
            }, {
                property: 'firstName',
                dataIndex: 'firstName',
                label: this.$tc('sw-customer.detailAddresses.columnFirstName')
            }, {
                property: 'company',
                dataIndex: 'company',
                label: this.$tc('sw-customer.detailAddresses.columnCompany')
            }, {
                property: 'street',
                label: this.$tc('sw-customer.detailAddresses.columnStreet'),
                dataIndex: 'street'
            }, {
                property: 'zipcode',
                dataIndex: 'zipcode',
                label: this.$tc('sw-customer.detailAddresses.columnZipCode'),
                align: 'right'
            }, {
                property: 'city',
                dataIndex: 'city',
                label: this.$tc('sw-customer.detailAddresses.columnCity')
            }];
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

            let address = this.activeCustomer.addresses.items[this.currentAddress.id];

            if (typeof address === 'undefined') {
                address = this.addressRepository.create(this.context, this.currentAddress.id);
            }

            Object.assign(address, this.currentAddress);
            this.addressRepository.save(address, this.context).then(() => {
                this.refreshList();
            });
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
            return this.addressRepository.create(this.context);
        },

        onEditAddress(id) {
            this.currentAddress = object.deepCopyObject(this.activeCustomer.addresses.items[id]);
            this.showEditAddressModal = id;
        },

        onDeleteAddress(id) {
            if (this.isDefaultAddress(id)) {
                return;
            }
            this.showDeleteAddressModal = id;
        },

        onConfirmDeleteAddress(id) {
            this.addressRepository.delete(id, this.context);
            this.refreshList();
            this.onCloseDeleteAddressModal();
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
                this.refreshList();
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
            this.activeCustomer.addresses.criteria.setPage(1);
            this.activeCustomer.addresses.criteria.setTerm(term);

            this.refreshList();
        },

        refreshList() {
            this.$refs.addressGrid.load();
        },

        createPrefix(string, replace) {
            const preFix = string.replace(replace, '');

            return `${preFix.charAt(0).toUpperCase()}${preFix.slice(1)}`;
        }
    }
});
