import template from './sw-settings-payment-create.html.twig';

const { Component } = Shopware;
const utils = Shopware.Utils;

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
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            this.paymentMethod = this.paymentMethodRepository.create(Shopware.Context.api, this.$route.params.id);
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.payment.detail', params: { id: this.paymentMethod.id } });
        },

        onSave() {
            this.$super('onSave');
        },
    },
});
