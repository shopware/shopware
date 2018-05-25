import { Component, Mixin } from 'src/core/shopware';
import template from './sw-customer-detail.html.twig';
import './sw-customer-detail.less';

Component.register('sw-customer-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('customer'),
        Mixin.getByName('applicationList'),
        Mixin.getByName('customerGroupList'),
        Mixin.getByName('paymentMethodList'),
        Mixin.getByName('countryList')
    ],

    beforeRouteLeave(to, from, next) {
        this.customerEditMode = false;
        next();
    },

    data() {
        return {
            customerEditMode: false
        };
    },

    computed: {
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

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.customerId = this.$route.params.id;
            }

            if (this.$route.name.includes('sw.customer.create')) {
                this.customerEditMode = true;
            }

            if (this.$route.params.edit === 'edit') {
                this.customerEditMode = true;
            }
        },

        onSave() {
            const customerName = this.customerName;
            const titleSaveSuccess = this.$tc('sw-customer.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: customerName });

            this.saveCustomer().then(() => {
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
