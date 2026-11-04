/**
 * @sw-package inventory
 */
import template from './sw-product-document-language-hint.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: ['change-language'],

    data() {
        return { defaultLanguageName: '' };
    },

    computed: {
        defaultLanguageId: () => Shopware.Context.api.systemLanguageId,
        isDefaultLanguage: () => Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId,
    },

    watch: {
        isDefaultLanguage: {
            immediate: true,
            async handler(isDefaultLanguage: boolean) {
                if (isDefaultLanguage || this.defaultLanguageName || !this.defaultLanguageId) {
                    return;
                }

                const language = await this.repositoryFactory
                    .create('language')
                    .get(this.defaultLanguageId)
                    .catch(() => {
                        // Only the optional label is missing; the link still switches to the known default language ID.
                        return null;
                    });
                this.defaultLanguageName = language?.name || '';
            },
        },
    },
});
