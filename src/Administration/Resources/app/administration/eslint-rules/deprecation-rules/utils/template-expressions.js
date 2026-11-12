/**
 * @sw-package framework
 *
 * Reads template text as JS expressions for the codemods that move element content into props,
 * e.g. a tab label into the `items` of `mt-tabs`.
 */

const espree = require('espree');

// vue-eslint-parser drops Twig tags from text nodes, so they are only visible in the source text
const TWIG_PATTERN = /{%|%}|{#|#}/;
const MUSTACHE_PATTERN = /^{{([\s\S]*)}}$/;

/**
 * Quotes a text as a single-quoted JS string.
 */
function quote(text) {
    return `'${text.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function isExpression(code) {
    try {
        const program = espree.parse(`(${code})`, { ecmaVersion: 'latest' });

        return program.body.length === 1 && program.body[0].type === 'ExpressionStatement';
    } catch {
        return false;
    }
}

/**
 * Makes an expression usable inside a double-quoted attribute: double-quoted strings become single-quoted.
 * Returns `null` when a double quote can't be replaced.
 */
function toAttributeExpression(code) {
    if (!code.includes('"')) {
        return code;
    }

    let tokens;

    try {
        tokens = espree.tokenize(code, { ecmaVersion: 'latest', range: true });
    } catch {
        return null;
    }

    let result = code;

    [...tokens].reverse().forEach((token) => {
        const inner = token.value.slice(1, -1);

        if (token.type === 'String' && token.value.startsWith('"') && !/['\\]/.test(inner)) {
            result = `${result.slice(0, token.range[0])}'${inner}'${result.slice(token.range[1])}`;
        }
    });

    return result.includes('"') ? null : result;
}

/**
 * Reads the content of an element as a JS expression for a prop. Plain text becomes a string literal,
 * a single interpolation like `{{ $t('key') }}` stays an expression, so the label is still translated.
 * Returns `{ code: null }` for empty content and `null` for markup, Twig or mixed content.
 */
function readContentExpression(sourceCode, element) {
    if (!element.endTag) {
        return { code: null };
    }

    if (element.children.some((child) => child.type === 'VElement')) {
        return null;
    }

    const inner = sourceCode.text.slice(element.startTag.range[1], element.endTag.range[0]);

    // `<` also catches HTML comments, which are no children
    if (TWIG_PATTERN.test(inner) || inner.includes('<')) {
        return null;
    }

    const text = inner.trim();

    if (text === '') {
        return { code: null };
    }

    // Interpolations are only parsed in .vue files, in .twig files they are part of the text
    const mustache = MUSTACHE_PATTERN.exec(text);

    if (mustache) {
        const code = mustache[1].trim();

        if (/{{|}}/.test(code) || !isExpression(code)) {
            return null;
        }

        const attributeCode = toAttributeExpression(code);

        return attributeCode === null ? null : { code: attributeCode };
    }

    if (/{{|}}|["&]/.test(text)) {
        return null;
    }

    return { code: quote(text.replace(/\s+/g, ' ')) };
}

module.exports = {
    TWIG_PATTERN,
    quote,
    toAttributeExpression,
    readContentExpression,
};
