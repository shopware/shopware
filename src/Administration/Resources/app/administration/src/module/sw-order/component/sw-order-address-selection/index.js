import { required } from 'src/core/service/validation.service';
import EntityValidationService from 'src/app/service/entity-validation.service';
import template from './sw-order-address-selection.html.twig';
import './sw-order-address-selection.scss';

/**
 * @sw-package checkout
 */

const { ShopwareError } = Shopware.Classes;
const { EntityDefinition, Mixin, Store } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'customSnippetApiService',
        'repositoryFactory',
    ],

    emits: ['change-address'],

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
            selectedAddressFormatting: '',
        };
    },

    computed: {
        order: () => Store.get('swOrderDetail').order,

        versionContext: () => Store.get('swOrderDetail').versionContext,

        orderCustomer() {
            return this.order.orderCustomer;
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        addressRepository() {
            return this.repositoryFactory.create(this.customer.addresses.entity, this.customer.addresses.source);
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        customerCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('addresses.country');

            return criteria;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('relations.entityName', 'customer_address')).addAssociation('customFields');

            return criteria;
        },

        addressOptions() {
            const addresses = (this.customer?.addresses || [])
                .map((item) => {
                    if (this.address && this.address.hash === item.hash) {
                        return null;
                    }

                    // Entity proxies may omit `id` when spread, so set it explicitly.
                    return {
                        ...item,
                        id: item.id,
                        label: this.addressLabel(item),
                    };
                })
                .filter((item) => item !== null);

            this.address &&
                addresses.unshift({
                    label: this.addressLabel(this.address),
                    ...this.address,
                });

            return addresses;
        },

        modalTitle() {
            return this.$t(
                `sw-order.addressSelection.${
                    this.currentAddress?._isNew ? 'modalTitleEditAddress' : 'modalTitleSelectAddress'
                }`,
            );
        },

        selectedAddressId() {
            return this.address?.customerAddressId ?? this.addressId;
        },

        selectedAddress() {
            return this.addressOptions.find((item) => item.id === this.selectedAddressId) ?? this.address;
        },
    },

    watch: {
        selectedAddress: {
            handler() {
                return this.renderSelectedAddress();
            },
            immediate: true,
        },

        currentAddress(newValue, oldValue) {
            if (newValue || !oldValue) {
                return;
            }

            this.clearAddressErrors(oldValue);
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.clearAddressErrors(this.currentAddress);
    },

    methods: {
        createdComponent() {
            this.getCustomer();
            this.getCustomFieldSet();
        },

        onEditAddress(id) {
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

            if (!this.isValidAddress(this.currentAddress)) {
                this.createNotificationError({
                    message: this.$t('sw-customer.notification.requiredFields'),
                });

                return Promise.reject();
            }

            // edit order address
            if (this.currentAddress.id === this.address.id) {
                return this.orderRepository
                    .save(this.order, this.versionContext)
                    .then(() => {
                        this.currentAddress = null;

                        this.onAddressChange(this.address.id, true);
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$t('sw-order.detail.messageSaveError'),
                        });
                    });
            }

            const address =
                this.customer.addresses.get(this.currentAddress.id) ??
                this.addressRepository.create(Shopware.Context.api, this.currentAddress.id);

            Object.assign(address, this.currentAddress);

            if (this.customer.addresses.has(address.id)) {
                this.customer.addresses.remove(address.id);
            }

            this.customer.addresses.push(address);

            return this.customerRepository.save(this.customer).then(() => {
                this.currentAddress = null;

                this.onAddressChange(address.id);
            });
        },

        isValidAddress(address) {
            const ignoreFields = ['createdAt'];
            const entityName = address.getEntityName();
            const requiredAddressFields = Object.keys(EntityDefinition.getRequiredFields(entityName));
            const errorStore = Shopware.Store.get('error');
            let isValid = true;

            requiredAddressFields.forEach((field) => {
                if (ignoreFields.includes(field)) {
                    return;
                }

                if (required(address[field])) {
                    this.removeRequiredFieldError(address, field);
                    return;
                }

                isValid = false;

                errorStore.addApiError({
                    expression: `${entityName}.${address.id}.${field}`,
                    error: new ShopwareError({
                        code: EntityValidationService.ERROR_CODE_REQUIRED,
                    }),
                });
            });

            return isValid;
        },

        clearAddressErrors(address) {
            if (!address) {
                return;
            }

            const entityName = address.getEntityName();
            const errorStore = Shopware.Store.get('error');
            const addressErrors = errorStore.getErrorsForEntity(entityName, address.id);

            if (!addressErrors) {
                return;
            }

            Object.keys(addressErrors).forEach((field) => this.removeRequiredFieldError(address, field));

            if (Object.keys(addressErrors).length === 0) {
                errorStore.removeApiError(`${entityName}.${address.id}`);
            }
        },

        removeRequiredFieldError(address, field) {
            const entityName = address.getEntityName();
            const errorStore = Shopware.Store.get('error');
            const error = errorStore.getApiErrorFromPath(entityName, address.id, [field]);

            if (error?.code !== EntityValidationService.ERROR_CODE_REQUIRED) {
                return;
            }

            errorStore.removeApiError(`${entityName}.${address.id}.${field}`);
        },

        onChangeDefaultAddress(data) {
            if (!data.value) {
                if (this.defaultShippingAddressId) {
                    this.customer.defaultShippingAddressId = this.defaultShippingAddressId;
                }

                if (this.defaultBillingAddressId) {
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

        onAddressChange(customerAddressId, edited = false) {
            this.$emit('change-address', {
                orderAddressId: this.orderAddressId,
                customerAddressId: customerAddressId,
                type: this.type,
                edited,
            });
        },

        getCustomer() {
            if (!this.orderCustomer.customerId) {
                return Promise.reject();
            }

            return this.customerRepository
                .get(this.orderCustomer.customerId, Shopware.Context.api, this.customerCriteria)
                .then((customer) => {
                    this.customer = customer;
                });
        },

        getCustomFieldSet() {
            return this.customFieldSetRepository.search(this.customFieldSetCriteria).then((customFieldSets) => {
                this.customerAddressCustomFieldSets = customFieldSets;
            });
        },

        renderSelectedAddress() {
            if (!this.selectedAddress || !this.customSnippetApiService) {
                this.selectedAddressFormatting = '';

                return Promise.resolve();
            }

            const selectedAddressId = this.selectedAddress.id;

            return this.customSnippetApiService
                .render(this.selectedAddress, this.selectedAddress.country?.addressFormat)
                .then((response) => {
                    if (this.selectedAddress?.id !== selectedAddressId) {
                        return;
                    }

                    this.selectedAddressFormatting = response.rendered;
                })
                .catch(() => {
                    this.selectedAddressFormatting = '';
                });
        },

        addressLabel(address) {
            const label = [
                [
                    address.company,
                    address.department,
                ]
                    .filter((v) => v)
                    .join(' - '),
                address.street,
                `${address.zipcode ?? ''} ${address.city}`.trim(),
                address?.countryState?.translated?.name,
                address?.country?.translated?.name,
            ];

            return label.filter((v) => v).join(', ');
        },
    },
};
