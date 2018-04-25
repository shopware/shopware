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
        Mixin.getByName('customer')
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

    updated() {
        if (this.$route.params.edit) {
            this.customerEditMode = true;
        }
    },

    methods: {
        onSave() {
            this.saveCustomer();
            this.customerEditMode = false;
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
        }
    }
});
