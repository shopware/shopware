/**
 * @sw-package framework
 */
/* eslint-disable @typescript-eslint/no-unsafe-member-access */
import { defineComponent, getApiContext, isString } from '../dependencies';

/* @private */

/**
 * @public
 *
 * Duplicated in `src/app/composables/use-placeholder`; change both together.
 */
export default defineComponent({
    methods: {
        placeholder<EntityName extends keyof EntitySchema.EntityKeys>(
            entity: Entity<EntityName>,
            field: keyof Entity<EntityName>,
            fallbackSnippet: string,
        ) {
            if (!entity) {
                return fallbackSnippet;
            }

            if (isString(entity[field]) && entity[field].length > 0) {
                return entity[field];
            }

            // Return the field from parent translation if set
            const language = getApiContext().language;
            const parentLanguageId = language ? language.parentId : null;

            // @ts-expect-error - we just check if translations exists
            const translations = entity.translations as unknown as { [key: string]: string }[];

            if (parentLanguageId && parentLanguageId.length > 0 && translations) {
                const translation = translations.find((entry) => {
                    return entry.id === `${entity.id}-${parentLanguageId}`;
                });

                // @ts-expect-error - we check if the field exists
                if (translation?.[field] && translation[field].length > 0) {
                    // @ts-expect-error - we check if the field exists beforehand
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                    return translation[field];
                }
            }

            // @ts-expect-error - we check if the field exists
            // Return the field from translated if set
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            if (entity.translated != null && entity.translated.hasOwnProperty(field)) {
                // @ts-expect-error - we check if the field exists beforehand
                if (entity.translated[field] !== null) {
                    // @ts-expect-error - we check if the field exists beforehand
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                    return entity.translated[field];
                }
            }

            // Return the placeholder snippet
            return fallbackSnippet;
        },
    },
});
