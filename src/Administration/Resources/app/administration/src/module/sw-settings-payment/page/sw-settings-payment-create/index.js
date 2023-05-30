import template from './sw-settings-payment-create.html.twig';

const utils = Shopware.Utils;

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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

        onSave() {
            this.$super('onSave').then(() => {
                this.$router.push({ name: 'sw.settings.payment.detail', params: { id: this.paymentMethod.id } });
            });
        },
    },
};
