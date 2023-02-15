import template from './sw-customer-create.html.twig';
import CUSTOMER from '../../constant/sw-customer.constant';

/**
 * @package customer-order
 */

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;
const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

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
            /**
             * @deprecated tag:v6.6.0 - salesChannels Will be removed due to unused
             * */
            salesChannels: null,
            isLoading: false,
            /**
             * @deprecated tag:v6.6.0 - errorEmailCustomer Will be removed due to unused
            * */
            errorEmailCustomer: null,
            /**
             * @deprecated tag:v6.6.0 - defaultMinPasswordLength will be removed due to unused
             * */
            defaultMinPasswordLength: null,
        };
    },

    computed: {
        ...mapPropertyErrors('address', [
            'company',
        ]),

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
            return this.loadLanguage(this.customer?.salesChannelId);
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
            if (value === CUSTOMER.ACCOUNT_TYPE_BUSINESS || !this.addressCompanyError) {
                return;
            }

            Shopware.State.dispatch(
                'error/removeApiError',
                {
                    expression: `customer_address.${this.address.id}.company`,
                },
            );
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
                return emailIsValid;
            }).catch((exception) => {
                Shopware.State.dispatch(
                    'error/addApiError',
                    {
                        expression: `customer.${this.customer.id}.email`,
                        error: new ShopwareError(exception.response.data.errors[0]),
                    },
                );
            });
        },

        async onSave() {
            this.isLoading = true;

            let hasError = false;
            const res = await this.validateEmail();
            if (!res || !res.isValid) {
                hasError = true;
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
                hasError = true;
            }

            if (hasError) {
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError'),
                });
                this.isLoading = false;
                return false;
            }

            const languageId = await this.languageId;
            const context = { ...Shopware.Context.api, ...{ languageId } };

            return numberRangePromise.then(() => {
                return this.customerRepository.save(this.customer, context).then((response) => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;

                    return response;
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-customer.detail.messageSaveError'),
                    });
                    this.isLoading = false;
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
         * @deprecated tag:v6.6.0 - getDefaultRegistrationConfig will be removed due to unused
         * */
        getDefaultRegistrationConfig() {
            this.systemConfigApiService.getValues('core.register').then((response) => {
                this.defaultMinPasswordLength = response['core.register.minPasswordLength'];
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
