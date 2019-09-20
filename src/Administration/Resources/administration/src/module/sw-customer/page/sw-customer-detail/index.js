import { mapPageErrors } from 'src/app/service/map-errors.service';
import template from './sw-customer-detail.html.twig';
import errorConfig from './error-config.json';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('discard-detail-page-changes')('customer')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onAbortButtonClick'
    },

    beforeRouteLeave(to, from, next) {
        this.customerEditMode = false;
        next();
    },

    data() {
        return {
            isLoading: false,
            customer: null,
            customerId: null,
            customerEditMode: false,
            customerGroups: [],
            salesChannels: [],
            countries: [],
            addresses: [],
            paymentMethods: [],
            customerAddressCustomFieldSets: [],
            customerCustomFieldSets: [],
            languages: [],
            language: {},
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.customer !== null ? this.salutation(this.customer) : '';
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },

        isCreateCustomer() {
            return this.$route.name.includes('sw.customer.create');
        },

        createMode() {
            return this.$route.name.includes('create');
        },

        isOrderPage() {
            return this.$route.name.includes('order');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        defaultCriteria() {
            const criteria = new Criteria();
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('salesChannel')
                .addAssociation('defaultPaymentMethod')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags');

            return criteria;
        },

        ...mapPageErrors(errorConfig)
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.$route.params.id) {
                this.customerId = this.$route.params.id;

                if (!this.createMode) {
                    this.customerRepository.get(
                        this.customerId,
                        this.context,
                        this.defaultCriteria
                    ).then((customer) => {
                        this.customer = customer;
                        this.languageRepository.get(this.customer.languageId, this.context).then((language) => {
                            this.language = language;
                            this.isLoading = false;
                        });
                        this.initializeFurtherComponents();
                    });
                } else {
                    this.initializeFurtherComponents();
                }
            }

            if (this.$route.params.edit === 'edit') {
                this.customerEditMode = true;
            }
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        initializeFurtherComponents() {
            if (!this.customer) {
                return;
            }

            this.isLoading = false;

            const criteria = new Criteria(1, 100);
            criteria.addAssociation('languages');
            this.salesChannelRepository.search(criteria, this.context).then((searchResult) => {
                searchResult.forEach((salesChannel) => {
                    if (salesChannel.id === this.customer.salesChannelId) {
                        this.languages = salesChannel.languages;
                    }
                });
                this.salesChannels = searchResult;
            });

            this.customerGroupRepository.search(new Criteria(1, 100), this.context).then((searchResult) => {
                this.customerGroups = searchResult;
            });

            const countryCriteria = new Criteria(1, 100);
            countryCriteria.addSorting(Criteria.sort('name'));
            this.countryRepository.search(countryCriteria, this.context).then((searchResult) => {
                this.countries = searchResult;
            });

            this.paymentMethodRepository.search(new Criteria(1, 100), this.context).then((searchResult) => {
                this.paymentMethods = searchResult;
            });

            this.customFieldSetRepository.search(
                this.buildCustomFieldCriteria('customer'),
                this.context
            ).then((searchResult) => {
                this.customerCustomFieldSets = searchResult.filter(set => set.customFields.length > 0);
            });

            this.customFieldSetRepository.search(
                this.buildCustomFieldCriteria('customer_address'),
                this.context
            ).then((searchResult) => {
                this.customerAddressCustomFieldSets = searchResult.filter(set => set.customFields.length > 0);
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.customerEditMode = false;
        },

        onSave() {
            if (!this.customerEditMode) {
                return false;
            }

            this.isSaveSuccessful = false;

            if (!this.customer.birthday) {
                this.customer.birthday = null;
            }
            this.isLoading = true;

            return this.customerRepository.save(this.customer, this.context).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                this.createdComponent();
            }).catch((exception) => {
                this.createNotificationError({
                    title: this.$tc('sw-customer.detail.titleSaveError'),
                    message: this.$tc('sw-customer.detail.messageSaveError')
                });
                this.isLoading = false;
                throw exception;
            });
        },

        onAbortButtonClick() {
            this.discardChanges();
            if (this.createMode === true) {
                this.$router.push({ name: 'sw.customer.index' });
                this.isLoading = false;
            }
            this.customerEditMode = false;
        },

        onActivateCustomerEditMode() {
            this.customerEditMode = true;
        },

        /**
         * @param {string} entity
         * @returns {Criteria}
         */
        buildCustomFieldCriteria(entity) {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('relations.entityName', entity));
            criteria.getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition'));

            return criteria;
        }
    }
});
