import template from './sw-settings-payment-create.html.twig';

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    methods: {
        createdComponent() {
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            this.paymentMethod = this.paymentMethodRepository.create();
        },

        onSave() {
            this.$super('onSave').then(() => {
                this.$router.push({ name: 'sw.settings.payment.detail', params: { id: this.paymentMethod.id } });
            });
        },
    },
};
