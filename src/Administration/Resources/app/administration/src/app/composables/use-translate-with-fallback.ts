/**
 * @sw-package framework
 */
import { useI18n } from 'vue-i18n';

/**
 * Composable alternative to the `translate-with-fallback` mixin.
 *
 * Resolves a snippet key with the current locale first and falls back to
 * `Shopware.Context.app.fallbackLocale` when the key is missing. Vue-i18n v10's
 * `te` only checks the active locale, so translations that live only in the
 * fallback locale (typically `en-GB`) would otherwise leak the raw key.
 *
 * The mixin used `this.$te` / `this.$t`; here they come from `useI18n()`. The
 * mixin is kept for legacy Options API components.
 *
 * @private
 */
export function useTranslateWithFallback(): {
    tWithFallback: (key: string) => string;
} {
    // Keep the composer object rather than destructuring: destructured `t`/`te`
    // trip @typescript-eslint/unbound-method since they are declared as methods.
    const i18n = useI18n();

    function tWithFallback(key: string): string {
        if (!key) {
            return '';
        }

        if (i18n.te(key)) {
            return i18n.t(key);
        }

        const fallbackLocale = Shopware.Context.app.fallbackLocale;
        if (fallbackLocale && i18n.te(key, fallbackLocale)) {
            return i18n.t(key, fallbackLocale);
        }

        return key;
    }

    return { tWithFallback };
}
