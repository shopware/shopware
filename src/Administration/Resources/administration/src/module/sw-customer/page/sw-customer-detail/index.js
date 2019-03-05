import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-customer-detail.html.twig';

Component.register('sw-customer-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('customer')
    ],

    beforeRouteLeave(to, from, next) {
        this.customerEditMode = false;
        next();
    },

    data() {
        return {
            customer: {},
            customerId: null,
            customerEditMode: false,
            customerGroups: [],
            salesChannels: [],
            countries: [],
            addresses: [],
            paymentMethods: [],
            languages: [],
            language: {}
        };
    },

    computed: {
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

        customerName() {
            const customer = this.customer;

            if (!customer.salutation && !customer.firstName && !customer.lastName) {
                return '';
            }

            const salutation = customer.salutation ? customer.salutation : '';
            const firstName = customer.firstName ? customer.firstName : '';
            const lastName = customer.lastName ? customer.lastName : '';

            return `${salutation} ${firstName} ${lastName}`;
        },

        isCreateCustomer() {
            return this.$route.name.includes('sw.customer.create');
        },

        createMode() {
            return this.$route.name.includes('create');
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

        initializeFurtherComponents() {
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
        },

        onSave() {
            const customerName = this.customerName;
            const titleSaveSuccess = this.$tc('sw-customer.detail.titleSaveSuccess');
            const titleSaveError = this.$tc('sw-customer.detail.titleSaveError');
            const messageSaveSuccess = this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: customerName });
            const messageSaveError = this.$tc('sw-customer.detail.messageSaveError');

            if (!this.customer.birthday) {
                this.customer.birthday = null;
            }

            return this.customer.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                throw exception;
            }).finally(() => {
                this.customerEditMode = false;
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
