/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
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
 * Composable alternative to the `salutation` mixin. The mixin exposed the filter itself as a computed
 * on the way to `salutation()`; here it is resolved inside the call, so nothing but the formatting
 * helper is public. The mixin stays in place for Options API components.
 *
 * Keep this and `src/app/mixin/salutation.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useSalutation(): {
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
