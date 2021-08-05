import { required } from 'src/core/service/validation.service';
import template from './sw-customer-detail-addresses.html.twig';
import './sw-customer-detail-addresses.scss';

const { Component, Mixin, EntityDefinition } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-detail-addresses', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
        customerEditMode: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            activeCustomer: this.customer,
            showAddAddressModal: false,
            showEditAddressModal: false,
            showDeleteAddressModal: false,
            addressSortProperty: null,
            addressSortDirection: '',
            currentAddress: null,
            customerAddressCustomFieldSets: null,
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customerAddressRepository() {
            return this.repositoryFactory.create('customer_address');
        },

        addressColumns() {
            return this.getAddressColumns();
        },

        addressRepository() {
            return this.repositoryFactory.create(
                this.activeCustomer.addresses.entity,
                this.activeCustomer.addresses.source,
            );
        },

        sortedAddresses() {
            if (this.addressSortProperty) {
                // eslint-disable-next-line vue/no-side-effects-in-computed-properties
                return this.activeCustomer.addresses.sort((a, b) => {
                    const aValue = a[this.addressSortProperty];
                    const bValue = b[this.addressSortProperty];

                    let isBigger = null;

                    if (typeof aValue === 'string' && typeof bValue === 'string') {
                        isBigger = aValue.toUpperCase() > bValue.toUpperCase();
                    }

                    if (typeof aValue === 'number' && typeof bValue === 'number') {
                        isBigger = (aValue - bValue) > 0;
                    }

                    if (isBigger !== null) {
                        if (this.addressSortDirection === 'DESC') {
                            return isBigger ? -1 : 1;
                        }

                        return isBigger ? 1 : -1;
                    }

                    return 0;
                });
            }

            return this.activeCustomer.addresses;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (!this.activeCustomer.id && this.$route.params.id) {
                this.customerRepository.get(this.$route.params.id).then((customer) => {
                    this.activeCustomer = customer;
                    this.isLoading = false;
                });
                return;
            }
            if (!this.activeCustomer.id) {
                this.$router.push({ name: 'sw.customer.detail.base', params: { id: this.$route.params.id } });
                return;
            }

            const customFieldSetCriteria = new Criteria();
            customFieldSetCriteria.addFilter(Criteria.equals('relations.entityName', 'customer_address'))
                .addAssociation('customFields');

            this.customFieldSetRepository.search(customFieldSetCriteria).then((customFieldSets) => {
                this.customerAddressCustomFieldSets = customFieldSets;
            });

            this.isLoading = false;
        },

        getAddressColumns() {
            return [{
                property: 'defaultShippingAddress',
                label: this.$tc('sw-customer.detailAddresses.columnDefaultShippingAddress'),
                align: 'center',
                iconLabel: 'default-shopping-cart',
            }, {
                property: 'defaultBillingAddress',
                label: this.$tc('sw-customer.detailAddresses.columnDefaultBillingAddress'),
                align: 'center',
                iconLabel: 'default-documentation-file',
            }, {
                property: 'lastName',
                label: this.$tc('sw-customer.detailAddresses.columnLastName'),
            }, {
                property: 'firstName',
                label: this.$tc('sw-customer.detailAddresses.columnFirstName'),
            }, {
                property: 'company',
                label: this.$tc('sw-customer.detailAddresses.columnCompany'),
            }, {
                property: 'street',
                label: this.$tc('sw-customer.detailAddresses.columnStreet'),
            }, {
                property: 'zipcode',
                label: this.$tc('sw-customer.detailAddresses.columnZipCode'),
                align: 'right',
            }, {
                property: 'city',
                label: this.$tc('sw-customer.detailAddresses.columnCity'),
            }];
        },

        setAddressSorting(column) {
            this.addressSortProperty = column.property;

            let direction = 'ASC';
            if (this.addressSortProperty === column.dataIndex) {
                if (this.addressSortDirection === direction) {
                    direction = 'DESC';
                }
            }
            this.addressSortProperty = column.dataIndex;
            this.addressSortDirection = direction;
        },

        onCreateNewAddress() {
            this.showAddAddressModal = true;
            this.createNewCustomerAddress();
        },

        createNewCustomerAddress() {
            const newAddress = this.addressRepository.create();
            newAddress.customerId = this.activeCustomer.id;

            this.currentAddress = newAddress;
        },

        onSaveAddress() {
            if (this.currentAddress === null) {
                return;
            }

            if (!this.isValidAddress(this.currentAddress)) {
                this.createNotificationError({
                    message: this.$tc('sw-customer.notification.requiredFields'),
                });
                return;
            }

            let address = this.activeCustomer.addresses.get(this.currentAddress.id);

            if (typeof address === 'undefined' || address === null) {
                address = this.addressRepository.create(Shopware.Context.api, this.currentAddress.id);
            }

            Object.assign(address, this.currentAddress);

            if (this.customer.addresses.has(address.id)) {
                this.customer.addresses.remove(address.id);
            }

            this.customer.addresses.push(address);

            this.currentAddress = null;
        },

        isValidAddress(address) {
            const ignoreFields = ['createdAt'];
            const requiredAddressFields = Object.keys(EntityDefinition.getRequiredFields('customer_address'));
            let isValid = true;

            isValid = requiredAddressFields.every(field => {
                return (ignoreFields.indexOf(field) !== -1) || required(address[field]);
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

        onEditAddress(id) {
            const currentAddress = this.addressRepository.create(Shopware.Context.api, id);

            // assign values and id to new address
            Object.assign(currentAddress, this.activeCustomer.addresses.get(id));

            this.currentAddress = currentAddress;
            this.showEditAddressModal = id;
        },

        onDeleteAddress(id) {
            if (this.isDefaultAddress(id)) {
                return;
            }
            this.showDeleteAddressModal = id;
        },

        onConfirmDeleteAddress(id) {
            this.onCloseDeleteAddressModal();

            return this.customerAddressRepository.delete(id).then(() => {
                this.refreshList();
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
            this.customer.defaultBillingAddressId = billingAddressId;
        },

        onChangeDefaultShippingAddress(shippingAddressId) {
            this.activeCustomer.defaultShippingAddressId = shippingAddressId;
            this.customer.defaultShippingAddressId = shippingAddressId;
        },

        onDuplicateAddress(addressId) {
            this.customerAddressRepository.clone(addressId).then(() => {
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
            this.customer[name] = data.id;
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
        },
    },
});
