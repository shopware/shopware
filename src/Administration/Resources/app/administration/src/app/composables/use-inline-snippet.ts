/**
 * @sw-package framework
 */

/**
 * Composable alternative to the `sw-inline-snippet` mixin's `getInlineSnippet`
 * helper. The mixin exposed the current/fallback locale as computed properties
 * used only to implement `getInlineSnippet`; here they are resolved inline from
 * the same global sources, so the composable is fully self-contained.
 *
 * The mixin is kept for legacy Options API components (and for any that read the
 * `swInlineSnippetLocale` computed directly — those keep the Options-API backoff
 * in the codemod).
 *
 * @private
 */
export function useInlineSnippet(): {
    getInlineSnippet: (value: { [key: string]: string }) => string | { [key: string]: string };
} {
    function currentLocale(): string {
        return Shopware.Store.get('session').currentLocale as unknown as string;
    }

    function fallbackLocale(): string {
        return Shopware.Context.app.fallbackLocale as unknown as string;
    }

    function getInlineSnippet(value: { [key: string]: string }): string | { [key: string]: string } {
        if (Shopware.Utils.types.isEmpty(value)) {
            return '';
        }
        if (value[currentLocale()]) {
            return value[currentLocale()];
        }
        if (value[fallbackLocale()]) {
            return value[fallbackLocale()];
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
