import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-payment-create.html.twig';

Component.extend('sw-settings-payment-create', 'sw-settings-payment-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.payment.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.paymentMethodStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.payment.detail', params: { id: this.paymentMethod.id } });
            });
        }
    }
});
