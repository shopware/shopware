import template from './sw-settings-shipping-create.html.twig';

const { Component, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

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
            return StateDeprecated.getStore('language');
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

            this.$super('createdComponent');

            this.shippingMethod.active = true;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.shipping.detail', params: { id: this.shippingMethod.id } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
