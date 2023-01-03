import template from './sw-customer-create.html.twig';
import CUSTOMER from '../../constant/sw-customer.constant';

/**
 * @package customer-order
 */

const { Mixin } = Shopware;

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
            address: null,
            customerNumberPreview: '',
            isSaveSuccessful: false,
            salesChannels: null,
            isLoading: false,
            errorEmailCustomer: null,
            /**
             * @deprecated tag:v6.6.0 - defaultMinPasswordLength Will be removed due to unused
             * */
            defaultMinPasswordLength: null,
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        validCompanyField() {
            return this.customer.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS ?
                this.address.company?.trim().length : true;
        },

        /**
         * @deprecated tag:v6.6.0 - validPasswordField will be removed due to unused
         * */
        validPasswordField() {
            return this.customer.password?.trim().length >= this.defaultMinPasswordLength;
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.State.commit('context/resetLanguageToDefault');

            this.customer = this.customerRepository.create();

            const addressRepository = this.repositoryFactory.create(
                this.customer.addresses.entity,
                this.customer.addresses.source,
            );

            this.customer.accountType = CUSTOMER.ACCOUNT_TYPE_PRIVATE;
            this.address = addressRepository.create();

            this.customer.addresses.add(this.address);
            this.customer.defaultBillingAddressId = this.address.id;
            this.customer.defaultShippingAddressId = this.address.id;
            this.customer.password = '';
            this.customer.vatIds = [];
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.customer.detail', params: { id: this.customer.id } });
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
                if (this.errorEmailCustomer) {
                    Shopware.State.dispatch(
                        'error/addApiError',
                        {
                            expression: `customer.${this.customer.id}.email`,
                            error: null,
                        },
                    );
                }

                return emailIsValid;
            }).catch((exception) => {
                Shopware.State.dispatch(
                    'error/addApiError',
                    {
                        expression: `customer.${this.customer.id}.email`,
                        error: exception.response.data.errors[0],
                    },
                );
            });
        },

        onSave() {
            this.isLoading = true;

            return this.validateEmail().then((res) => {
                if (!res || !res.isValid) {
                    this.createNotificationError({
                        message: this.$tc('sw-customer.detail.messageSaveError'),
                    });
                    this.isLoading = false;

                    return Promise.reject(new Error('The given email already exists.'));
                }

                this.isSaveSuccessful = false;
                let numberRangePromise = Promise.resolve();
                if (this.customerNumberPreview === this.customer.customerNumber) {
                    numberRangePromise = this.numberRangeService
                        .reserve('customer', this.customer.salesChannelId).then((response) => {
                            this.customerNumberPreview = 'reserved';
                            this.customer.customerNumber = response.number;
                        });
                }

                if (!this.validCompanyField) {
                    this.createErrorMessageForCompanyField();
                    return false;
                }

                return numberRangePromise.then(() => {
                    this.customerRepository.save(this.customer).then(() => {
                        this.isLoading = false;
                        this.isSaveSuccessful = true;
                    }).catch(() => {
                        this.createNotificationError({
                            message: this.$tc('sw-customer.detail.messageSaveError'),
                        });
                        this.isLoading = false;
                    });
                });
            });
        },

        onChangeSalesChannel(salesChannelId) {
            this.customer.salesChannelId = salesChannelId;
            this.numberRangeService.reserve('customer', salesChannelId, true).then((response) => {
                this.customerNumberPreview = response.number;
                this.customer.customerNumber = response.number;
            });
        },

        createErrorMessageForCompanyField() {
            this.isLoading = false;
            Shopware.State.dispatch('error/addApiError', {
                expression: `customer_address.${this.address.id}.company`,
                error: new Shopware.Classes.ShopwareError(
                    {
                        code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                    },
                ),
            });

            this.createNotificationError({
                message: this.$tc('sw-customer.error.COMPANY_IS_REQUIRED'),
            });
        },

        /**
         * @deprecated tag:v6.6.0 - getDefaultRegistrationConfig Will be removed due to unused
         * */
        getDefaultRegistrationConfig() {
            this.systemConfigApiService.getValues('core.register').then((response) => {
                this.defaultMinPasswordLength = response['core.register.minPasswordLength'];
            });
        },
    },
};
