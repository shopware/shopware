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
            const customerAddressesStore = this.customer.getAssociationStore('addresses');

            const defaultAddress = customerAddressesStore.create();
            this.customer.addresses = [
                defaultAddress
            ];

            this.$super.createdComponent();

            this.customerEditMode = true;

            defaultAddress.customerId = this.customer.id;

            // ToDo: Change to actual password strategy
            this.customer.password = 'shopware';

            this.customer.defaultBillingAddressId = defaultAddress.id;
            this.customer.defaultShippingAddressId = defaultAddress.id;
        },

        onSave() {
            this.customer.save().then((customer) => {
                this.$router.push({ name: 'sw.customer.detail', params: { id: customer.id } });
            });
        }
    }
});
