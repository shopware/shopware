/**
 * @package admin
 *
 * This plugin allows to load html.twig template files.
 */

const isTwigFile = /\.twig$/;
const isTwigRawFile = /\.twig\?raw$/;
const isHTMLFile = /\.html$/;
const isHTMLRawFile = /\.html\?raw$/;

export default () => ({
    name: 'shopware-twigjs-plugin',

    async transform(fileContent, id) {
        if (id.endsWith('src/Administration/Resources/app/administration/index.html')) {
            return;
        }

        if (!(isTwigFile.test(id) || isHTMLFile.test(id) || isTwigRawFile.test(id) || isHTMLRawFile.test(id))) {
            return;
        }

        // Remove all HTML comments (including multi-line comments)
        fileContent = fileContent.replace(/<!--[\s\S]*?-->/gm, '');

        // Remove all newline characters
        fileContent = fileContent.replace(/\n/g, '');

        // Escape double quotes by adding backslashes
        fileContent = fileContent.replace(/"/g, '\\"');

        // Escape dollar signs by adding backslashes
        fileContent = fileContent.replace(/\$/g, '\\$');

        // Replace all sequences of 2 or more spaces with a single space
        fileContent = fileContent.replace(/ {2,}/g, ' ');

        const code = `export default \"${fileContent}\"`;

        // eslint-disable-next-line consistent-return
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
});
