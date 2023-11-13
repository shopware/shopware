import { required } from 'src/core/service/validation.service';
import template from './sw-customer-detail-addresses.html.twig';
import './sw-customer-detail-addresses.scss';

/**
 * @package checkout
 */

const { ShopwareError } = Shopware.Classes;
const { Mixin, EntityDefinition } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addFilter(Criteria.equals('salutationKey', 'not_specified'));

            return criteria;
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

            const customFieldSetCriteria = new Criteria(1, 25);
            customFieldSetCriteria.addFilter(Criteria.equals('relations.entityName', 'customer_address'));

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
                iconLabel: 'regular-shopping-cart',
                iconTooltip: this.$tc('sw-customer.detailAddresses.columnDefaultShippingAddress'),
            }, {
                property: 'defaultBillingAddress',
                label: this.$tc('sw-customer.detailAddresses.columnDefaultBillingAddress'),
                align: 'center',
                iconLabel: 'regular-file-text',
                iconTooltip: this.$tc('sw-customer.detailAddresses.columnDefaultBillingAddress'),
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

        async createNewCustomerAddress() {
            const defaultSalutationId = await this.getDefaultSalutation();

            const newAddress = this.addressRepository.create();
            newAddress.customerId = this.activeCustomer.id;
            newAddress.salutationId = defaultSalutationId;

            this.currentAddress = newAddress;
        },

        onSaveAddress() {
            if (this.currentAddress === null || !this.isValidAddress(this.currentAddress)) {
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

            requiredAddressFields.forEach(field => {
                if ((ignoreFields.indexOf(field) !== -1) || required(address[field])) {
                    return;
                }

                isValid = false;

                Shopware.State.dispatch(
                    'error/addApiError',
                    {
                        expression: `customer_address.${this.currentAddress.id}.${field}`,
                        error: new ShopwareError(
                            {
                                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                            },
                        ),
                    },
                );
            });

            return isValid;
        },

        onCloseAddressModal() {
            if (this.defaultShippingAddressId) {
                this.activeCustomer.defaultShippingAddressId = this.defaultShippingAddressId;
            }

            if (this.defaultBillingAddressId) {
                this.activeCustomer.defaultBillingAddressId = this.defaultBillingAddressId;
            }

            if (this.$route.query.detailId) {
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

        async onDuplicateAddress(addressId) {
            const { id } = await this.customerAddressRepository.clone(addressId);
            const newAddress = await this.customerAddressRepository.get(id);

            this.activeCustomer.addresses.push(newAddress);
        },

        onChangeDefaultAddress(data) {
            if (!data.value) {
                if (this.defaultShippingAddressId) {
                    this.activeCustomer.defaultShippingAddressId = this.defaultShippingAddressId;
                }

                if (this.defaultBillingAddressId) {
                    this.activeCustomer.defaultBillingAddressId = this.defaultBillingAddressId;
                }
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

        async getDefaultSalutation() {
            const res = await this.salutationRepository.searchIds(this.salutationCriteria);

            return res.data?.[0];
        },
    },
};
