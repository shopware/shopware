import './sw-customer-detail.scss';
import template from './sw-customer-detail.html.twig';
import errorConfig from '../../error-config.json';
import CUSTOMER from '../../constant/sw-customer.constant';

/**
 * @package customer-order
 */

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-customer-detail', {
    template,

    inject: [
        'repositoryFactory',
        'customerGroupRegistrationService',
        'acl',
        'customerValidationService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('discard-detail-page-changes')('customer'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onAbortButtonClick',
    },

    props: {
        customerId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            customer: null,
            customerAddressCustomFieldSets: [],
            customerCustomFieldSets: [],
            errorEmailCustomer: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.customer !== null ? this.salutation(this.customer) : '';
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        editMode: {
            get() {
                if (typeof this.$route.query.edit === 'boolean') {
                    return this.$route.query.edit;
                }

                return this.$route.query.edit === 'true';
            },
            set(editMode) {
                this.$router.push({ name: this.$route.name, query: { edit: editMode } });
            },
        },

        defaultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('salesChannel')
                .addAssociation('defaultPaymentMethod')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags')
                .addAssociation('requestedGroup');

            criteria
                .getAssociation('addresses')
                .addSorting(Criteria.sort('firstName'), 'ASC', false);

            return criteria;
        },

        generalRoute() {
            return {
                name: 'sw.customer.detail.base',
                params: { id: this.customerId },
                query: { edit: this.editMode },
            };
        },

        addressesRoute() {
            return {
                name: 'sw.customer.detail.addresses',
                params: { id: this.customerId },
                query: { edit: this.editMode },
            };
        },

        ordersRoute() {
            return {
                name: 'sw.customer.detail.order',
                params: { id: this.customerId },
                query: { edit: this.editMode },
            };
        },

        emailHasChanged() {
            const origin = this.customer.getOrigin();
            if (this.customer.isNew() || !origin.email) {
                return true;
            }

            return origin.email !== this.customer.email;
        },

        validCompanyField() {
            return this.customer.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS ?
                this.customer.company?.trim().length : true;
        },

        ...mapPageErrors(errorConfig),
    },

    watch: {
        customerId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-customer-detail__customer',
                path: 'customer',
                scope: this,
            });
            this.isLoading = true;

            this.customerRepository.get(
                this.customerId,
                Shopware.Context.api,
                this.defaultCriteria,
            ).then((customer) => {
                this.customer = customer;
                this.customer.accountType = this.customer.company?.trim().length ?
                    CUSTOMER.ACCOUNT_TYPE_BUSINESS : CUSTOMER.ACCOUNT_TYPE_PRIVATE;
                this.isLoading = false;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.editMode = false;
        },

        validateEmail() {
            const { id, email, boundSalesChannelId } = this.customer;

            return this.customerValidationService.checkCustomerEmail({
                id,
                email,
                boundSalesChannelId,
            }).then((emailIsValid) => {
                if (this.errorEmailCustomer) {
                    Shopware.State.dispatch('error/addApiError',
                        {
                            expression: `customer.${this.customer.id}.email`,
                            error: null,
                        });
                }

                return emailIsValid;
            }).catch((exception) => {
                this.emailIsValid = false;
                Shopware.State.dispatch('error/addApiError',
                    {
                        expression: `customer.${this.customer.id}.email`,
                        error: exception.response.data.errors[0],
                    });
            });
        },

        async onSave() {
            this.isLoading = true;

            if (!this.editMode) {
                return false;
            }

            if (this.customer.email && this.emailHasChanged) {
                const response = await this.validateEmail();

                if (!response || !response.isValid) {
                    this.isLoading = false;
                    return false;
                }
            }

            if (!this.validCompanyField) {
                this.createErrorMessageForCompanyField();
                return false;
            }

            this.isSaveSuccessful = false;

            if (!this.customer.birthday) {
                this.customer.birthday = null;
            }

            if (!(await this.validPassword(this.customer))) {
                this.isLoading = false;
                return false;
            }

            if (this.customer.passwordNew) {
                this.customer.password = this.customer.passwordNew;
            }

            if (this.customer.accountType === CUSTOMER.ACCOUNT_TYPE_PRIVATE) {
                this.customer.company = null;
            }

            return this.customerRepository.save(this.customer).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                this.createdComponent();
                this.createNotificationSuccess({
                    message: this.$tc('sw-customer.detail.messageSaveSuccess', 0, {
                        name: `${this.customer.firstName} ${this.customer.lastName}`,
                    }),
                });
            }).catch((exception) => {
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError'),
                });
                this.isLoading = false;
                throw exception;
            });
        },

        onAbortButtonClick() {
            this.discardChanges();
            this.editMode = false;
        },

        onActivateCustomerEditMode() {
            this.editMode = true;
        },

        abortOnLanguageChange() {
            return this.customerRepository.hasChanges(this.customer);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.createdComponent();
        },

        async validPassword(customer) {
            const { passwordNew, passwordConfirm } = customer;
            const passwordSet = (passwordNew || passwordConfirm);
            const passwordNotEquals = (passwordNew !== passwordConfirm);

            if (passwordSet) {
                if (passwordNotEquals) {
                    this.createNotificationError({
                        message: this.$tc('sw-customer.detail.notificationPasswordErrorMessage'),
                    });

                    return false;
                }
            }

            return true;
        },

        acceptCustomerGroupRegistration() {
            this.customerGroupRegistrationService.accept(this.customer.id).then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-customer.customerGroupRegistration.acceptMessage'),
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-customer.customerGroupRegistration.errorMessage'),
                });
            }).finally(() => {
                this.createdComponent();
            });
        },

        declineCustomerGroupRegistration() {
            this.customerGroupRegistrationService.decline(this.customer.id).then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-customer.customerGroupRegistration.declineMessage'),
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-customer.customerGroupRegistration.errorMessage'),
                });
            }).finally(() => {
                this.createdComponent();
            });
        },

        createErrorMessageForCompanyField() {
            this.isLoading = false;
            Shopware.State.dispatch('error/addApiError', {
                expression: `customer.${this.customer.id}.company`,
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
    },
});
