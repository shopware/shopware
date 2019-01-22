import { Mixin, State } from 'src/core/shopware';

Mixin.register('placeholder', {
    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    methods: {
        placeholder(entity, field, fallbackSnippet) {
            if (!entity) {
                return fallbackSnippet;
            }

            // Return the field from parent translation if set
            const parentLanguageId = this.languageStore.getCurrentLanguage().parentId;
            if (parentLanguageId && parentLanguageId.length > 0 && entity.translations) {
                const translation = entity.translations.find((entry) => {
                    return entry.id === `${entity.id}-${parentLanguageId}`;
                });

                if (translation && translation[field] && translation[field].length > 0) {
                    return translation[field];
                }
            }

            // Return the field from viewData if set
            if (entity.meta != null && entity.meta.viewData[field] !== null && entity.meta.viewData[field].length > 0) {
                return entity.meta.viewData[field];
            }

            // Return the placeholder snippet
            return fallbackSnippet;
        }
    }
});
