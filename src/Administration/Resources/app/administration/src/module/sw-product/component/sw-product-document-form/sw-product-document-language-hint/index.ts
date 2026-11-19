/**
 * @sw-package inventory
 */
import type Repository from 'src/core/data/repository.data';
import template from './sw-product-document-language-hint.html.twig';

const { Context, Utils } = Shopware;

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: ['close'],

    computed: {
        languageRepository(): Repository<'language'> {
            return this.repositoryFactory.create('language');
        },
    },

    data() {
        return {
            defaultLanguageName: '',
        };
    },

    async created() {
        const { systemLanguageId } = Context.api;
        if (!systemLanguageId) {
            return;
        }

        try {
            const language = await this.languageRepository.get(systemLanguageId, Context.api, {
                cacheKey: ['shared-data', 'language', systemLanguageId],
            });
            this.defaultLanguageName = language?.name ?? '';
        } catch {
            this.defaultLanguageName = '';
        }
    },

    methods: {
        switchToDefaultLanguage(): void {
            Utils.EventBus.emit('on-change-language-clicked', Context.api.systemLanguageId);
            this.$emit('close');
        },
    },
});
