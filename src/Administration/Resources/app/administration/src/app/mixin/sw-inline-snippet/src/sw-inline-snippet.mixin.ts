/**
 * @sw-package framework
 */
import { defineComponent, getAppContext, isEmpty, isObject, useSessionStore } from '../dependencies';

/**
 * @public
 *
 * Duplicated in `src/app/composables/use-inline-snippet`; change both together.
 */
export default defineComponent({
    computed: {
        swInlineSnippetLocale(): string {
            return useSessionStore().currentLocale as unknown as string;
        },

        swInlineSnippetFallbackLocale(): string {
            return getAppContext().fallbackLocale as unknown as string;
        },
    },

    methods: {
        getInlineSnippet(value: { [key: string]: string }) {
            if (isEmpty(value)) {
                return '';
            }
            if (value[this.swInlineSnippetLocale]) {
                return value[this.swInlineSnippetLocale];
            }
            if (value[this.swInlineSnippetFallbackLocale]) {
                return value[this.swInlineSnippetFallbackLocale];
            }
            if (isObject(value)) {
                const locale = Object.keys(value).find((key) => {
                    return value[key] !== '';
                });

                if (locale !== undefined) {
                    return value[locale];
                }
            }

            return value;
        },
    },
});
