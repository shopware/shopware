/**
 * @sw-package framework
 *
 * The `{{ … }}` interpolations of an `.html.twig` template as expressions.
 *
 * The patched template parser keeps `{{ … }}` in `.twig` files as plain text, because it is Twig syntax there, so
 * ESLint never visits these expressions. The upgrade codemods need them anyway: `{{ $t('…') }}` and
 * `{{ getDeliveryState(item).translated.name }}` are the most common places of the code they rewrite.
 * This helper parses the text of a `VText` node like a `.vue` template and moves the ranges of the resulting nodes
 * to the linted source, so fixes and codemod comments work on them as on any other template node.
 * Their `loc` stays relative to the parsed text; use `locOf()` for reports.
 */

const vueParser = require('vue-eslint-parser');
const { walk } = require('./component-tags');

const OPEN = '<template>';
const TWIG_COMMENT = /\{#[\s\S]*?#\}/g;

/**
 * The names that `v-for` and slot scopes of the enclosing elements define.
 *
 * @param {object} node a template node
 * @returns {Set<string>}
 */
function scopeVariables(node) {
    const names = new Set();

    for (let current = node; current; current = current.parent) {
        (current.variables ?? []).forEach((variable) => names.add(variable.id.name));
    }

    return names;
}

/**
 * @param {import('eslint').SourceCode} sourceCode
 * @param {object} textNode VText of an `.html.twig` template
 * @returns {Array<{ container: object, expression: object, references: object[] }>} `references` are the
 *     identifiers the expression reads from the component instance
 */
function twigInterpolations(sourceCode, textNode) {
    const text = sourceCode.text.slice(textNode.range[0], textNode.range[1]);

    if (!text.includes('{{')) {
        return [];
    }

    // Twig comments are text as well; blank them out, keeping every offset
    const masked = text.replace(TWIG_COMMENT, (comment) => comment.replace(/[^\n]/g, ' '));
    let templateBody;

    try {
        templateBody = vueParser.parseForESLint(`${OPEN}${masked}</template>`, {
            filePath: 'interpolation.vue',
            ecmaVersion: 2022,
            sourceType: 'module',
        }).ast.templateBody;
    } catch {
        return [];
    }

    const delta = textNode.range[0] - OPEN.length;
    const locals = scopeVariables(textNode);

    return (templateBody?.children ?? [])
        .filter((child) => child.type === 'VExpressionContainer' && child.expression)
        .map((container) => {
            walk(container, (node) => {
                node.range = [node.range[0] + delta, node.range[1] + delta];
            });

            return {
                container,
                expression: container.expression,
                references: container.references
                    .filter((reference) => !reference.variable && !locals.has(reference.id.name))
                    .map((reference) => reference.id),
            };
        });
}

/**
 * The location of a node from its range, also for nodes of `twigInterpolations()`.
 *
 * @param {import('eslint').SourceCode} sourceCode
 * @param {object} node
 */
function locOf(sourceCode, node) {
    return { start: sourceCode.getLocFromIndex(node.range[0]), end: sourceCode.getLocFromIndex(node.range[1]) };
}

module.exports = {
    twigInterpolations,
    locOf,
};
