import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-settings-shipping-create', 'sw-settings-shipping-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.shipping.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.shippingMethodStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
            // This is actual a required parameter
            this.shippingMethod.type = 0;
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.shipping.detail', params: { id: this.shippingMethod.id } });
            });
        }
    }
});
