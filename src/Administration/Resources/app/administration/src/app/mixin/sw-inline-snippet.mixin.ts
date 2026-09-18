/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';
import { types } from 'shopware:utils';
import useSessionStore from 'shopware:stores/session';

/**
 * @private
 *
 * Duplicated in `src/app/composables/use-inline-snippet`; change both together.
 */
export default Shopware.Mixin.register(
    'sw-inline-snippet',
    defineComponent({
        computed: {
            swInlineSnippetLocale(): string {
                return useSessionStore().currentLocale as unknown as string;
            },

            swInlineSnippetFallbackLocale(): string {
                return Shopware.Context.app.fallbackLocale as unknown as string;
            },
        },

        methods: {
            getInlineSnippet(value: { [key: string]: string }) {
                if (types.isEmpty(value)) {
                    return '';
                }
                if (value[this.swInlineSnippetLocale]) {
                    return value[this.swInlineSnippetLocale];
                }
                if (value[this.swInlineSnippetFallbackLocale]) {
                    return value[this.swInlineSnippetFallbackLocale];
                }
                if (types.isObject(value)) {
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
    }),
);
