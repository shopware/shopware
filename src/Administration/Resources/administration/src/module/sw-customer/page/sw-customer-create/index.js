import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-customer-create.html.twig';

Component.extend('sw-customer-create', 'sw-customer-detail', {
    template,

    inject: ['numberRangeService'],
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.customer.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    data() {
        return {
            customerNumberPreview: ''
        };
    },

    provide() {
        return {
            swCustomerCreateOnChangeSalesChannel: this.onChangeSalesChannel
        };
    },


    methods: {
        createdComponent() {
            this.customer = this.customerStore.create(this.$route.params.id);
            const customerAddressesStore = this.customer.getAssociation('addresses');

            const defaultAddress = customerAddressesStore.create();
            defaultAddress.customerId = this.customer.id;

            this.customer.defaultBillingAddressId = defaultAddress.id;
            this.customer.defaultShippingAddressId = defaultAddress.id;

            // ToDo: Change to actual password strategy
            this.customer.password = 'shopware';

            this.$super.createdComponent();

            this.customerEditMode = true;
        },

        onSave() {
            if (this.customerNumberPreview === this.customer.customerNumber) {
                this.numberRangeService.reserve('customer', this.customer.salesChannelId).then((response) => {
                    this.customerNumberPreview = 'reserved';
                    this.customer.customerNumber = response.number;
                    this.$super.onSave().then(() => {
                        this.$router.push({ name: 'sw.customer.detail', params: { id: this.customer.id } });
                    });
                });
            } else {
                this.$super.onSave().then(() => {
                    this.customerNumberPreview = 'reserved';
                    this.$router.push({ name: 'sw.customer.detail', params: { id: this.customer.id } });
                });
            }
        },

        onChangeSalesChannel(salesChannelId) {
            this.numberRangeService.reserve('customer', salesChannelId, true).then((response) => {
                this.customerNumberPreview = response.number;
                this.customer.customerNumber = response.number;
            });
        }
    }
});
