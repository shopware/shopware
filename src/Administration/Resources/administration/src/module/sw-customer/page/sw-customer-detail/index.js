import { Component, Mixin } from 'src/core/shopware';
import template from './sw-customer-detail.html.twig';
import './sw-customer-detail.less';

Component.register('sw-customer-detail', {
    template,

    data() {
        return {
            customerEditMode: false
        };
    },

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('customer'),
        Mixin.getByName('applicationList'),
        Mixin.getByName('customerGroupList'),
        Mixin.getByName('paymentMethodList')
    ],

    beforeRouteLeave(to, from, next) {
        this.customerEditMode = false;
        next();
    },

    created() {
        if (this.$route.params.id) {
            this.customerId = this.$route.params.id;
        }

        if (this.$route.name.includes('sw.customer.create')) {
            this.customerEditMode = true;
        }
    },

    methods: {
        onSave() {
            this.saveCustomer().then(() => {
                this.customerEditMode = false;
                this.createNotificationSuccess({
                    title: this.$tc('sw-customer.detail.titleSaveSuccess'),
                    message: this.$tc('sw-customer.detail.messageSaveSuccess', this.customerName, {
                        name: this.customerName
                    })
                });
            });
        },

        onDisableCustomerEditMode() {
            this.customerEditMode = false;
        },

        onActivateCustomerEditMode() {
            this.customerEditMode = true;
        }
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
    }
});
