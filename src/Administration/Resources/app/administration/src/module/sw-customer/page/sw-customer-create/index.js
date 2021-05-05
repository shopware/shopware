import template from './sw-customer-create.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-customer-create', {
    template,

    inject: [
        'repositoryFactory',
        'numberRangeService',
        'systemConfigApiService',
        'customerValidationService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            customer: null,
            address: null,
            customerNumberPreview: '',
            isSaveSuccessful: false,
            salesChannels: null,
            isLoading: false,
            errorEmailCustomer: null
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('customer');
        }
    },

    watch: {
        'customer.salesChannelId'(salesChannelId) {
            this.systemConfigApiService
                .getValues('core.systemWideLoginRegistration').then(response => {
                    if (response['core.systemWideLoginRegistration.isCustomerBoundToSalesChannel']) {
                        this.customer.boundSalesChannelId = salesChannelId;
                    }
                });
        }
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
                this.customer.addresses.source
            );

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

            return this.customerValidationService.checkCustomerEmail({
                id,
                email,
                boundSalesChannelId
            }).then((emailIsValid) => {
                if (this.errorEmailCustomer) {
                    Shopware.State.dispatch('error/addApiError',
                        {
                            expression: `customer.${this.customer.id}.email`,
                            error: null
                        });
                }

                return emailIsValid;
            }).catch((exception) => {
                Shopware.State.dispatch('error/addApiError',
                    {
                        expression: `customer.${this.customer.id}.email`,
                        error: exception.response.data.errors[0]
                    });
            });
        },

        onSave() {
            this.isLoading = true;
            if (this.customer.email) {
                this.validateEmail().then((response) => {
                    if (!response || !response.isValid) {
                        this.isLoading = false;
                    }
                });
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

            numberRangePromise.then(() => {
                this.customerRepository.save(this.customer).then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-customer.detail.messageSaveError')
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
        }
    }
});
