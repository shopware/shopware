/**
 * @sw-package framework
 */

type SalutationFilterEntityType = {
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
