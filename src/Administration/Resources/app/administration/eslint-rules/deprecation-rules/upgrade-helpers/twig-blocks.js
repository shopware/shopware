/**
 * @sw-package framework
 *
 * Finds Twig blocks in Administration templates. The `twig-vue` processor hands `.html.twig` files to the
 * Vue parser, so `{% block %}` tags are plain text there and have to be read from the source.
 */

const BLOCK = /\{%-?\s*block\s+([A-Za-z0-9_]+)\s*-?%\}/g;
const END_BLOCK = /\{%-?\s*endblock(?:\s+([A-Za-z0-9_]+))?\s*-?%\}/g;

/**
 * Every `{% block name %}` with the range of the tag and of the name.
 *
 * @param {string} text
 * @returns {Array<{ name: string, range: [number, number], nameRange: [number, number] }>}
 */
function findBlocks(text) {
    return [...text.matchAll(BLOCK)].map((match) => {
        const nameStart = match.index + match[0].indexOf(match[1], match[0].indexOf('block') + 5);

        return {
            name: match[1],
            range: [match.index, match.index + match[0].length],
            nameRange: [nameStart, nameStart + match[1].length],
        };
    });
}

/**
 * Every named `{% endblock name %}` with the range of the name.
 *
 * @param {string} text
 * @returns {Array<{ name: string, nameRange: [number, number] }>}
 */
function findNamedEndBlocks(text) {
    return [...text.matchAll(END_BLOCK)]
        .filter((match) => match[1])
        .map((match) => {
            const nameStart = match.index + match[0].lastIndexOf(match[1]);

            return { name: match[1], nameRange: [nameStart, nameStart + match[1].length] };
        });
}

module.exports = {
    findBlocks,
    findNamedEndBlocks,
};
