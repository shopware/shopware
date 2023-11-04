import template from './sw-order-new-customer-modal.html.twig';
import './sw-order-new-customer-modal.scss';
import CUSTOMER from '../../../sw-customer/constant/sw-customer.constant';

/**
 * @package customer-order
 */

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'numberRangeService',
        'systemConfigApiService',
        'customerValidationService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            customer: null,
            /**
             * @deprecated tag:v6.6.0 - salesChannels Will be removed due to unused
             * */
            salesChannels: null,
            isLoading: false,
            customerNumberPreview: '',
        };
    },

    computed: {
        ...mapPageErrors({
            'sw.order.new.customer.detail': {
                customer: [
                    'firstName',
                    'lastName',
                    'email',
                    'salesChannelId',
                    'customerNumber',
                    'defaultPaymentMethodId',
                    'groupId',
                ],
            },

            'sw.order.new.customer.address': {
                customer_address: [
                    'firstName',
                    'lastName',
                    'street',
                    'city',
                    'countryId',
                ],
            },
        }),

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        addressRepository() {
            return this.repositoryFactory.create('customer_address');
        },

        shippingAddress() {
            if (this.isSameBilling) {
                return this.billingAddress;
            }

            return this.customer !== null ? this.customer.addresses.get(this.customer.defaultShippingAddressId) : null;
        },

        billingAddress() {
            return this.customer !== null ? this.customer.addresses.get(this.customer.defaultBillingAddressId) : null;
        },

        isSameBilling: {
            get() {
                if (this.customer === null) {
                    return true;
                }

                return this.customer.defaultBillingAddressId === this.customer.defaultShippingAddressId;
            },

            set(newValue) {
                if (newValue === true) {
                    this.customer.defaultShippingAddressId = this.customer.defaultBillingAddressId;

                    // remove all addresses but default billing...
                    if (this.customer.isNew()) {
                        this.customer.addresses = this.customer.addresses.filter((address) => {
                            return address.id === this.customer.defaultBillingAddressId;
                        });
                    }

                    return;
                }

                const shippingAddress = this.addressRepository.create();
                this.customer.addresses.add(shippingAddress);
                this.customer.defaultShippingAddressId = shippingAddress.id;
            },
        },

        validCompanyField() {
            return this.customer.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS ?
                this.customer.company?.trim().length : true;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageCriteria() {
            const criteria = new Criteria();
            criteria.setLimit(1);

            if (this.customer?.salesChannelId) {
                criteria.addFilter(
                    Criteria.equals('salesChannelDefaultAssignments.id', this.customer.salesChannelId),
                );
            }

            return criteria;
        },

        languageId() {
            return this.loadLanguage(this.customer.salesChannelId);
        },
    },

    watch: {
        'customer.salesChannelId'(salesChannelId) {
            this.systemConfigApiService
                .getValues('core.systemWideLoginRegistration').then(response => {
                    if (response['core.systemWideLoginRegistration.isCustomerBoundToSalesChannel']) {
                        this.customer.boundSalesChannelId = salesChannelId;
                    }
                });
        },

        'customer.accountType'(value) {
            if (value === CUSTOMER.ACCOUNT_TYPE_BUSINESS) {
                return;
            }

            Shopware.State.dispatch(
                'error/removeApiError',
                {
                    expression: `customer_address.${this.billingAddress.id}.company`,
                },
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customer = this.customerRepository.create();

            const billingAddress = this.addressRepository.create();
            this.customer.addresses.add(billingAddress);

            this.customer.defaultShippingAddressId = billingAddress.id;
            this.customer.defaultBillingAddressId = billingAddress.id;
            this.customer.accountType = CUSTOMER.ACCOUNT_TYPE_PRIVATE;
            this.customer.vatIds = [];
        },

        async onSave() {
            let hasError = false;

            const res = await this.validateEmail();

            if (!res || !res.isValid) {
                hasError = true;
            }

            if (!this.validCompanyField) {
                this.createErrorMessageForCompanyField();
                hasError = true;
            }

            if (this.customer.accountType === CUSTOMER.ACCOUNT_TYPE_PRIVATE) {
                this.customer.vatIds = [];
            }

            if (hasError) {
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError'),
                });

                this.isLoading = false;
                return false;
            }

            let numberRangePromise = Promise.resolve();
            if (this.customerNumberPreview === this.customer.customerNumber) {
                numberRangePromise = this.numberRangeService
                    .reserve('customer', this.customer.salesChannelId).then((response) => {
                        this.customerNumberPreview = response.number;
                        this.customer.customerNumber = response.number;
                    });
            }

            return numberRangePromise.then(() => {
                return this.saveCustomer();
            });
        },

        async saveCustomer() {
            const languageId = await this.languageId;

            const context = { ...Shopware.Context.api, ...{ languageId } };

            return this.customerRepository.save(this.customer, context).then((response) => {
                this.$emit('on-select-existing-customer', this.customer.id);
                this.isLoading = false;

                this.onClose();

                return response;
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError'),
                });
                this.isLoading = false;
            });
        },

        onChangeSalesChannel(salesChannelId) {
            this.customer.salesChannelId = salesChannelId;
            this.numberRangeService.reserve('customer', salesChannelId, true).then((response) => {
                this.customerNumberPreview = response.number;
                this.customer.customerNumber = response.number;
            });
        },

        onClose() {
            this.$emit('close');
        },

        createErrorMessageForCompanyField() {
            Shopware.State.dispatch('error/addApiError', {
                expression: `customer_address.${this.billingAddress.id}.company`,
                error: new Shopware.Classes.ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                }),
            });
        },

        validateEmail() {
            const { id, email, boundSalesChannelId } = this.customer;

            if (!email) {
                return Promise.resolve({ isValid: true });
            }

            return this.customerValidationService.checkCustomerEmail({
                id,
                email,
                boundSalesChannelId,
            }).then((emailIsValid) => {
                return emailIsValid;
            }).catch((exception) => {
                if (!exception) {
                    return;
                }

                Shopware.State.dispatch('error/addApiError', {
                    expression: `customer.${this.customer.id}.email`,
                    error: exception?.response?.data?.errors[0],
                });
            });
        },

        async loadLanguage(salesChannelId) {
            const languageId = Shopware.Context.api.languageId;

            if (!salesChannelId) {
                return languageId;
            }

            const res = await this.languageRepository.searchIds(this.languageCriteria);

            if (!res?.data) {
                return languageId;
            }

            return res.data[0];
        },
    },
};
