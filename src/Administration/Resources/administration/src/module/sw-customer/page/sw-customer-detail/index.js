import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-customer-detail.html.twig';

Component.register('sw-customer-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('discard-detail-page-changes')('customer')
    ],

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

        customerStore() {
            return State.getStore('customer');
        },

        customerGroupStore() {
            return State.getStore('customer_group');
        },

        countryStore() {
            return State.getStore('country');
        },

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        customerAddressStore() {
            return this.customer.getAssociation('addresses');
        },

        languageStore() {
            return State.getStore('language');
        },

        paymentMethodStore() {
            return State.getStore('payment_method');
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

        customFieldSetStore() {
            return State.getStore('custom_field_set');
        }
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

                if (this.createMode) {
                    this.customer = this.customerStore.getById(this.customerId);
                    this.initializeFurtherComponents();
                } else {
                    this.customerStore.getByIdAsync(this.customerId).then((customer) => {
                        this.customer = customer;
                        this.languageStore.getByIdAsync(this.customer.languageId).then((language) => {
                            this.language = language;
                            this.isLoading = false;
                        });
                        this.initializeFurtherComponents();
                    });
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
            this.customerAddressStore.getList({
                limit: 10,
                page: 1
            });


            this.salesChannelStore.getList({ page: 1, limit: 100 }).then((response) => {
                response.items.forEach((salesChannel) => {
                    if (salesChannel.id === this.customer.salesChannelId) {
                        salesChannel.getAssociation('languages').getList({ page: 1, limit: 100 });
                        this.languages = salesChannel.languages;
                    }
                });
                this.salesChannels = response.items;
            });

            this.customerGroupStore.getList({ page: 1, limit: 100 }).then((response) => {
                this.customerGroups = response.items;
            });

            this.countryStore.getList({ page: 1, limit: 100, sortBy: 'name' }).then((response) => {
                this.countries = response.items;
            });

            this.paymentMethodStore.getList({ page: 1, limit: 100 }).then((response) => {
                this.paymentMethods = response.items;
            });

            this.customFieldSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'customer'),
                associations: {
                    customFields: {
                        limit: 100,
                        sort: 'config.customFieldPosition'
                    }
                }
            }, true).then((response) => {
                this.customerCustomFieldSets = response.items;
            });

            this.customFieldSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'customer_address'),
                associations: {
                    customFields: {
                        limit: 100,
                        sort: 'config.customFieldPosition'
                    }
                }
            }, true).then((response) => {
                this.customerAddressCustomFieldSets = response.items;
            });
        },

        saveFinish() {
            this.customerEditMode = false;
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;

            if (!this.customer.birthday) {
                this.customer.birthday = null;
            }
            this.isLoading = true;

            return this.customer.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
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
        }
    }
});
