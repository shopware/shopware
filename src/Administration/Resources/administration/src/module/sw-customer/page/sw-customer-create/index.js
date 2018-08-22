import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-customer-create.html.twig';

Component.extend('sw-customer-create', 'sw-customer-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.customer.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
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
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.customer.detail', params: { id: this.customer.id } });
            });
        }
    }
});
