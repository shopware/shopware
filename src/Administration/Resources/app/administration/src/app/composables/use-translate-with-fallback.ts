/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { useI18n } from 'vue-i18n';

/**
 * Composable alternative to the `translate-with-fallback` mixin.
 *
 * Resolves a snippet key against the active locale first and against
 * `Shopware.Context.app.fallbackLocale` after that. Vue-i18n v10's `te` only looks at the active
 * locale, so a translation that exists only in the fallback locale (typically `en-GB`) would
 * otherwise leak the raw key into the UI.
 *
 * The mixin read `this.$te`/`this.$t`; here both come from `useI18n()`. The mixin stays in place for
 * Options API components.
 *
 * Keep this and `src/app/mixin/translate-with-fallback.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useTranslateWithFallback(): {
    tWithFallback: (key: string) => string;
} {
    // The composer object is kept instead of destructuring `t`/`te`: they are declared as methods, so
    // destructured references trip @typescript-eslint/unbound-method.
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
            // The locale has to travel in TranslateOptions: a positional second string argument binds
            // to the `t(key, defaultMsg)` overload and renders the locale itself. The mixin's
            // `this.$t(key, fallbackLocale)` worked because legacy `$t` read that position as a locale.
            return i18n.t(key, {}, { locale: fallbackLocale });
        }

        return key;
    }

    return { tWithFallback };
}
