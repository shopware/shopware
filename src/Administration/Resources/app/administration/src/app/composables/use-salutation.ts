/**
 * @sw-package framework
 */

/** @private */
export type SalutationFilterEntityType = {
    salutation: {
        id: string;
        salutationKey: string;
        displayName: string;
    };
    title: string;
    firstName: string;
    lastName: string;
    [key: string]: unknown;
};

/**
 * Composable alternative to the `salutation` mixin. Duplicated from the mixin;
 * the mixin is kept for legacy Options API components.
 *
 * Keep this and `src/app/mixin/salutation.mixin.ts` in sync — change both together.
 *
 * @private
 */
export function useSalutation(): {
    salutation: (entity: SalutationFilterEntityType, fallbackSnippet?: string) => string;
} {
    function salutation(entity: SalutationFilterEntityType, fallbackSnippet = ''): string {
        const salutationFilter = Shopware.Filter.getByName('salutation') as (
            entity: SalutationFilterEntityType,
            fallbackSnippet: string,
        ) => string;

        return salutationFilter(entity, fallbackSnippet);
    }

    return { salutation };
}
