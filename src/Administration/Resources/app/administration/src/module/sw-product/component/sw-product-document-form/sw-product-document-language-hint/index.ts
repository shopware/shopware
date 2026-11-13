/**
 * @sw-package inventory
 */
import template from './sw-product-document-language-hint.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: ['language-change'],

    data() {
        return { defaultLanguageName: '' };
    },

    computed: {
        languageId() {
            return Shopware.Store.get('context').api.languageId;
        },

        defaultLanguageId() {
            return Shopware.Store.get('context').api.systemLanguageId;
        },

        isDefaultLanguage(): boolean {
            return this.languageId === this.defaultLanguageId;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },
    },

    watch: {
        isDefaultLanguage: {
            immediate: true,
            async handler(isDefaultLanguage: boolean) {
                if (isDefaultLanguage || this.defaultLanguageName || !this.defaultLanguageId) {
                    return;
                }

                const language = await this.languageRepository.get(this.defaultLanguageId).catch(() => {
                    // Only the optional label is missing; the link still switches to the known default language ID.
                    return null;
                });
                this.defaultLanguageName = language?.name || '';
            },
        },
    },
});
