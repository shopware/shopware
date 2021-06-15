const { Mixin } = Shopware;
const types = Shopware.Utils.types;

Mixin.register('placeholder', {
    methods: {
        placeholder(entity, field, fallbackSnippet) {
            if (!entity) {
                return fallbackSnippet;
            }

            if (types.isString(entity[field]) && entity[field].length > 0) {
                return entity[field];
            }

            // TODO: Refactor with NEXT-3304
            // Return the field from parent translation if set
            const parentLanguageId = Shopware.Context.api.language ? Shopware.Context.api.language.parentId : null;
            if (parentLanguageId && parentLanguageId.length > 0 && entity.translations) {
                const translation = entity.translations.find((entry) => {
                    return entry.id === `${entity.id}-${parentLanguageId}`;
                });

                if (translation?.[field] && translation[field].length > 0) {
                    return translation[field];
                }
            }

            // Return the field from translated if set
            if (entity.translated != null && entity.translated.hasOwnProperty(field)) {
                if (entity.translated[field] !== null) {
                    return entity.translated[field];
                }
            }

            // Return the placeholder snippet
            return fallbackSnippet;
        },
    },
});
