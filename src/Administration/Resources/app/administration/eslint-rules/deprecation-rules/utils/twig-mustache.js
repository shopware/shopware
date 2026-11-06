/**
 * @sw-package framework
 *
 * The Vue parser only parses `{{ … }}` interpolations in `.vue` files. In `.html.twig` templates it
 * keeps them as plain text, so the upgrade codemods search that text for the members they look for.
 */

/**
 * Every match of `pattern` inside the `{{ … }}` interpolations of a text node, with its offset in the file.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {{ type: string, range: [number, number] }} textNode
 * @param {RegExp} pattern a global regular expression
 * @returns {{ match: RegExpMatchArray, offset: number }[]}
 */
function mustacheMatches(sourceCode, textNode, pattern) {
    if (textNode.type !== 'VText') {
        return [];
    }

    const raw = sourceCode.text.slice(textNode.range[0], textNode.range[1]);
    const results = [];

    for (const mustache of raw.matchAll(/\{\{([\s\S]*?)\}\}/g)) {
        const expressionStart = textNode.range[0] + mustache.index + 2;

        for (const match of mustache[1].matchAll(pattern)) {
            results.push({ match, offset: expressionStart + match.index });
        }
    }

    return results;
}

/**
 * A pattern for free references to one of the names, e.g. `dateFilter(` but not `item.dateFilter`.
 *
 * @param {string[]} names
 */
function referencePattern(names) {
    return new RegExp(`(?<![\\w$.])(${names.join('|')})(?![\\w$])`, 'g');
}

module.exports = {
    mustacheMatches,
    referencePattern,
};
