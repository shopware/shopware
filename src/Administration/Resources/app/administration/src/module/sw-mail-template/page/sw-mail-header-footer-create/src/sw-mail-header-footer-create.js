/**
 * @sw-package after-sales
 */

import template from './sw-mail-header-footer-create.html.twig';
import { getApiContext, useContextStore } from '../dependencies';

/**
 * @sw-package after-sales
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

            this.mailHeaderFooter = this.mailHeaderFooterRepository.create(getApiContext(), this.$route.params.id ?? null);
            this.mailHeaderFooterId = this.mailHeaderFooter.id;

            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.mail.template.detail_head_foot',
                params: { id: this.mailHeaderFooterId },
            });
        },

        onSave() {
            this.isLoading = true;
            this.$super('onSave');
        },
    },
};
