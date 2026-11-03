import template from './sw-settings-payment-create.html.twig';
import { useContextStore } from '../dependencies';

/**
 * @sw-package checkout
 *
 * @public
 */
export default {
    template,

    methods: {
        createdComponent() {
            if (!useContextStore().isSystemDefaultLanguage) {
                useContextStore().resetLanguageToDefault();
            }

            this.paymentMethod = this.paymentMethodRepository.create();
        },

        onSave() {
            this.$super('onSave').then(() => {
                this.$router.push({
                    name: 'sw.settings.payment.detail',
                    params: { id: this.paymentMethod.id },
                });
            });
        },
    },
};
