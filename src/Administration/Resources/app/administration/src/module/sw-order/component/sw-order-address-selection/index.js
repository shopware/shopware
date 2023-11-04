import { required } from 'src/core/service/validation.service';
import template from './sw-order-address-selection.html.twig';
import './sw-order-address-selection.scss';

/**
 * @package customer-order
 */

const { EntityDefinition, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        address: {
            type: Object,
            required: false,
            default: () => {},
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        addressId: {
            type: String,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        type: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            customer: {},
            currentAddress: null,
            customerAddressCustomFieldSets: null,
            orderAddressId: cloneDeep(this.address?.id),
        };
    },

    computed: {
        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
        ]),

        orderCustomer() {
            return this.order.orderCustomer;
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        addressRepository() {
            return this.repositoryFactory.create(
                this.customer.addresses.entity,
                this.customer.addresses.source,
            );
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        customerCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('addresses');

            return criteria;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('relations.entityName', 'customer_address'))
                .addAssociation('customFields');

            return criteria;
        },

        addressOptions() {
            const addresses = (this.customer?.addresses || []).map(item => {
                return {
                    label: `${item.street}, ${item.zipcode} ${item.city}`,
                    ...item,
                };
            });

            // eslint-disable-next-line no-unused-expressions
            this.address && addresses.unshift({
                label: `${this.address.street}, ${this.address.zipcode} ${this.address.city}`,
                ...this.address,
            });

            return addresses;
        },

        modalTitle() {
            return this.$tc(
                `sw-order.addressSelection.${this.currentAddress?._isNew
                    ? 'modalTitleEditAddress'
                    : 'modalTitleSelectAddress'}`,
            );
        },

        selectedAddressId() {
            return this.address?.customerAddressId ?? this.addressId;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getCustomer();
            this.getCustomFieldSet();
        },

        onEditAddress(id) {
            // set address selected
            this.orderAddressId = id;

            if (id === this.address.id) {
                this.currentAddress = this.address;
                return;
            }

            const currentAddress = this.addressRepository.create(Shopware.Context.api, id);

            this.currentAddress = Object.assign(currentAddress, this.customer.addresses.get(id));
        },

        onCreateNewAddress() {
            this.createNewCustomerAddress();
        },

        createNewCustomerAddress() {
            const newAddress = this.addressRepository.create();
            newAddress.customerId = this.customer.id;

            this.currentAddress = newAddress;
        },

        onSaveAddress() {
            if (this.currentAddress === null) {
                return Promise.resolve();
            }

            // edit order address
            if (this.currentAddress.id === this.address.id) {
                return this.orderRepository.save(this.order, this.versionContext).then(() => {
                    this.currentAddress = null;
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-order.detail.messageSaveError'),
                    });
                });
            }

            if (!this.isValidAddress(this.currentAddress)) {
                this.createNotificationError({
                    message: this.$tc('sw-customer.notification.requiredFields'),
                });

                return Promise.reject();
            }

            const address = this.customer.addresses.get(this.currentAddress.id) ??
                this.addressRepository.create(Shopware.Context.api, this.currentAddress.id);

            Object.assign(address, this.currentAddress);

            if (this.customer.addresses.has(address.id)) {
                this.customer.addresses.remove(address.id);
            }

            this.customer.addresses.push(address);

            return this.customerRepository.save(this.customer).then(() => {
                this.currentAddress = null;
            });
        },

        isValidAddress(address) {
            const ignoreFields = ['createdAt'];
            const requiredAddressFields = Object.keys(EntityDefinition.getRequiredFields('customer_address'));

            return requiredAddressFields.every(field => (ignoreFields.indexOf(field) !== -1) || required(address[field]));
        },

        onChangeDefaultAddress(data) {
            if (!data.value) {
                if (this.hasOwnProperty('defaultShippingAddressId')) {
                    this.customer.defaultShippingAddressId = this.defaultShippingAddressId;
                }

                if (this.hasOwnProperty('defaultBillingAddressId')) {
                    this.customer.defaultBillingAddressId = this.defaultBillingAddressId;
                }
                return;
            }

            const preFix = this.createPrefix(data.name, '-address');
            const name = `default${preFix}AddressId`;

            this[name] = this.customer[name];
            this.customer[name] = data.id;
        },

        createPrefix(string, replace) {
            const preFix = string.replace(replace, '');

            return `${preFix.charAt(0).toUpperCase()}${preFix.slice(1)}`;
        },

        onAddressChange(customerAddressId) {
            this.$emit('change-address', {
                orderAddressId: this.addressId,
                customerAddressId,
                type: this.type,
            });
        },

        getCustomer() {
            if (!this.orderCustomer.customerId) {
                return Promise.reject();
            }

            return this.customerRepository.get(
                this.orderCustomer.customerId,
                Shopware.Context.api,
                this.customerCriteria,
            ).then((customer) => {
                this.customer = customer;
            });
        },

        getCustomFieldSet() {
            return this.customFieldSetRepository
                .search(this.customFieldSetCriteria)
                .then((customFieldSets) => {
                    this.customerAddressCustomFieldSets = customFieldSets;
                });
        },
    },
};
