import path from 'path';
import type { Plugin } from 'vite';

/**
 * @sw-package framework
 *
 * This plugin allows to load html.twig template files.
 */

const isTwigFile = /\.twig$/;
const isHTMLFile = /\.html$/;

// Resolved from this file, so it also matches when Shopware is installed in `vendor/shopware/administration`.
const administrationIndexHtml = path.resolve(__dirname, '../../../index.html').replace(/\\/g, '/');

// Hands an edited template to the running Administration, which falls back to a page reload when it cannot apply it.
function serveModule(template: string): string {
    return `const template = "${template}";
export default template;

if (import.meta.hot) {
    import.meta.hot.accept((next) => {
        Promise.resolve(next && globalThis.Shopware?.Application?.view?.hotUpdateTemplate?.(template, next.default))
            .catch(() => false)
            .then((updated) => updated || import.meta.hot.invalidate());
    });
}`;
}

/* @private */
export default function twigPlugin(): Plugin {
    let isServe = false;

    return {
        name: 'shopware-vite-plugin-twigjs',

        configResolved(config) {
            isServe = config.command === 'serve';
        },

        transform(fileContent, id) {
            if (id === administrationIndexHtml) {
                return;
            }

            if (!(isTwigFile.test(id) || isHTMLFile.test(id))) {
                return;
            }

            // Trim the content and remove HTML comments
            fileContent = fileContent
                .replace(/<!--[\s\S]*?-->/gm, '') // Remove HTML comments first
                .trim()
                .replace(/\s+/g, ' '); // Normalize whitespace

            // Escape characters that might break the string
            fileContent = fileContent
                .replace(/\\/g, '\\\\') // Escape backslashes first
                .replace(/"/g, '\\"') // Escape double quotes
                .replace(/\$/g, '\\$') // Escape dollar signs
                .replace(/\n/g, ' ') // Replace newlines with spaces
                .replace(/\r/g, ' '); // Replace carriage returns with spaces

            if (isServe) {
                return { code: serveModule(fileContent), map: null };
            }

            const code = `export default "${fileContent}"`;

            return {
                code,
                ast: {
                    type: 'Program',
                    start: 0,
                    end: code.length,
                    body: [
                        {
                            type: 'ExportDefaultDeclaration',
                            start: 0,
                            end: code.length,
                            declaration: {
                                type: 'Literal',
                                start: 15,
                                end: code.length,
                                value: fileContent,
                                raw: `"${fileContent}"`,
                            },
                        },
                    ],
                    sourceType: 'module',
                },
                map: null,
            };
        },
    };
}
