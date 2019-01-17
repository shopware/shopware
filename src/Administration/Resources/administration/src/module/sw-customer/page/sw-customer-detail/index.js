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
            paymentMethods: []
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
            if (this.$route.params.id) {
                this.customerId = this.$route.params.id;
                this.customer = this.customerStore.getById(this.customerId);

                this.customerAddressStore.getList({
                    limit: 10,
                    page: 1
                });

                this.salesChannelStore.getList({ page: 1, limit: 100 }).then((response) => {
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
            }

            if (this.$route.params.edit === 'edit') {
                this.customerEditMode = true;
            }
        },

        onSave() {
            const customerName = this.customerName;
            const titleSaveSuccess = this.$tc('sw-customer.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: customerName });

            return this.customer.save().then(() => {
                this.customerEditMode = false;
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        },

        onDisableCustomerEditMode() {
            this.customerEditMode = false;
        },

        onActivateCustomerEditMode() {
            this.customerEditMode = true;
        }
    }
});
