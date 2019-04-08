import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-shipping-create.html.twig';

Component.extend('sw-settings-shipping-create', 'sw-settings-shipping-detail', {
    template,
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.shipping.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.shippingMethodStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.shipping.detail', params: { id: this.shippingMethod.id } });
            });
        }
    }
});
