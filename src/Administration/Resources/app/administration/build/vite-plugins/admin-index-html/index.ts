/**
 * @sw-package framework
 */
import type { Plugin } from 'vite';

const PLACEHOLDER = /<%-\s*([\w.]+)\s*%>/g;

/**
 * @private
 *
 * Renders the `<%- path.to.value %>` placeholders of the `index.html` of the dev server.
 */
export default function AdminIndexHtmlPlugin(data: Record<string, unknown>): Plugin {
    return {
        name: 'shopware-vite-plugin-admin-index-html',
        apply: 'serve',
        // Vite 8 silently ignores the old `{ enforce, transform }` hook shape and would parse the raw placeholders.
        transformIndexHtml: {
            order: 'pre',
            handler: (html) =>
                html.replace(PLACEHOLDER, (_, key: string) => {
                    const value = key
                        .split('.')
                        .reduce<unknown>((current, part) => (current as Record<string, unknown> | undefined)?.[part], data);

                    // Like EJS, primitives and Buffers are rendered with `String()`
                    // eslint-disable-next-line @typescript-eslint/no-base-to-string
                    return value === undefined || value === null ? '' : String(value);
                }),
        },
    };
}
