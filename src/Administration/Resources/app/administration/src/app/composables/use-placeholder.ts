/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access */

/**
 * Composable alternative to the `placeholder` mixin.
 *
 * The mixin is a pure helper with no `this` usage, so the logic is duplicated
 * here verbatim and the mixin is kept untouched for legacy Options API
 * components. New `<script setup>` components (and the SFC migration codemod)
 * use this composable.
 *
 * @private
 */
export function usePlaceholder(): {
    placeholder: <EntityName extends keyof EntitySchema.Entities>(
        entity: Entity<EntityName>,
        field: keyof Entity<EntityName>,
        fallbackSnippet: string,
    ) => string;
} {
    function placeholder<EntityName extends keyof EntitySchema.Entities>(
        entity: Entity<EntityName>,
        field: keyof Entity<EntityName>,
        fallbackSnippet: string,
    ): string {
        if (!entity) {
            return fallbackSnippet;
        }

        if (Shopware.Utils.types.isString(entity[field]) && entity[field].length > 0) {
            return entity[field];
        }

        // Return the field from parent translation if set
        const parentLanguageId = Shopware.Context.api.language ? Shopware.Context.api.language.parentId : null;

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
    }

    return { placeholder };
}
