/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */

/**
 * Composable alternative to the `sw-inline-snippet` mixin's `getInlineSnippet` helper.
 *
 * The mixin exposed the current and fallback locale as computeds that existed only to implement
 * `getInlineSnippet`; here they are read inline from the same globals, so nothing but the helper is
 * public. The mixin stays in place for Options API components.
 *
 * Keep this and `src/app/mixin/sw-inline-snippet.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useInlineSnippet(): {
    getInlineSnippet: (value: { [key: string]: string }) => string | { [key: string]: string };
} {
    function getInlineSnippet(value: { [key: string]: string }): string | { [key: string]: string } {
        if (Shopware.Utils.types.isEmpty(value)) {
            return '';
        }

        const currentLocale = Shopware.Store.get('session').currentLocale as unknown as string;

        if (value[currentLocale]) {
            return value[currentLocale];
        }

        const fallbackLocale = Shopware.Context.app.fallbackLocale as unknown as string;

        if (value[fallbackLocale]) {
            return value[fallbackLocale];
        }

        if (Shopware.Utils.types.isObject(value)) {
            const locale = Object.keys(value).find((key) => {
                return value[key] !== '';
            });

            if (locale !== undefined) {
                return value[locale];
            }
        }

        return value;
    }

    return { getInlineSnippet };
}
