import { Component, Entity } from 'src/core/shopware';
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
            if (this.$route.params.id) {
                this.customerStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();

            this.customerEditMode = true;

            const defaultAddress = Entity.getRawEntityObject('customer_address');

            defaultAddress.id = utils.createId();
            defaultAddress.customerId = this.customer.id;

            this.customer.addresses.push(defaultAddress);
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
