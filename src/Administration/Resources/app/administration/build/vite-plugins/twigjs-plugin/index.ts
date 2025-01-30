import type { Plugin } from 'vite';

/**
 * @package framework
 *
 * This plugin allows to load html.twig template files.
 */

const isTwigFile = /\.twig$/;
const isTwigRawFile = /\.twig\?raw$/;
const isHTMLFile = /\.html$/;
const isHTMLRawFile = /\.html\?raw$/;

/* @private */
export default function twigPlugin(): Plugin {
    return {
        name: 'shopware-vite-plugin-twigjs',

        transform(fileContent, id) {
            if (id.endsWith('src/Administration/Resources/app/administration/index.html')) {
                return;
            }

            if (!(isTwigFile.test(id) || isHTMLFile.test(id) || isTwigRawFile.test(id) || isHTMLRawFile.test(id))) {
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
