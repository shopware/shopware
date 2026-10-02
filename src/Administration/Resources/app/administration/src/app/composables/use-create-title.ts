/**
 * @sw-package framework
 */

import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

/**
 * Setup-mode equivalent of the `$createTitle` global property: builds the browser title from the
 * admin's own name, the current route's module title and an optional record identifier, most
 * specific part first.
 *
 * Keep this and `initTitle()` in `src/app/adapter/view/vue.adapter.ts` in sync — change both
 * together. The one deliberate difference is the translator: the global property reached for
 * `this.$root.$t`, this uses the calling component's own `useI18n()`, which resolves to the same
 * global messages unless that component declares an `i18n` scope of its own.
 *
 * @private
 */
export default function useCreateTitle(): (identifier?: string | null, ...additionalParams: string[]) => string {
    const { t } = useI18n();
    const route = useRoute();

    return function createTitle(identifier: string | null = null, ...additionalParams: string[]): string {
        const moduleTitle = (route.meta?.$module as { title?: string } | undefined)?.title;

        if (!moduleTitle) {
            return '';
        }

        return [
            t('global.sw-admin-menu.textShopwareAdmin'),
            t(moduleTitle),
            identifier,
            ...additionalParams,
        ]
            .filter((item): item is string => item !== null && item.trim() !== '')
            .reverse()
            .join(' | ');
    };
}
