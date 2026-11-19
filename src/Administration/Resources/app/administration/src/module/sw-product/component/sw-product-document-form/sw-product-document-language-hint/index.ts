/**
 * @sw-package inventory
 */
import template from './sw-product-document-language-hint.html.twig';

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: ['close'],

    data() {
        return {
            defaultLanguageName: '',
        };
    },

    async created() {
        const { systemLanguageId } = Shopware.Context.api;
        if (!systemLanguageId) {
            return;
        }

        try {
            const language = await this.repositoryFactory.create('language').get(systemLanguageId, Shopware.Context.api, {
                cacheKey: ['shared-data', 'language', systemLanguageId],
            });
            this.defaultLanguageName = language?.name ?? '';
        } catch {
            this.defaultLanguageName = '';
        }
    },

    methods: {
        switchToDefaultLanguage(): void {
            Shopware.Utils.EventBus.emit('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
            this.$emit('close');
        },
    },
});
