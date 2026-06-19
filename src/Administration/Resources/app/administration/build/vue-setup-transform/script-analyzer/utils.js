/**
 * @sw-package framework
 */

const { parse } = require('@babel/parser');
const { ShopwareSetupTransformError } = require('../utils/transform-error');

/**
 * @typedef {import('@babel/types').File} BabelFile
 * @typedef {import('@babel/types').Node} BabelNode
 *
 * @typedef {object} SourceRange
 * @property {number} start
 * @property {number} end
 */

/**
 * Converts Babel source ranges into the transform's compact range shape.
 *
 * @param {BabelNode} node
 * @param {number} scriptOffset
 * @returns {SourceRange}
 */
function getNodeRange(node, scriptOffset) {
    if (typeof node.start !== 'number' || typeof node.end !== 'number') {
        throw new ShopwareSetupTransformError(
            'Missing source range metadata while transforming Shopware setup.',
            scriptOffset,
        );
    }

    return {
        start: node.start,
        end: node.end,
    };
}

/**
 * Parses user setup code with the plugins required by the declared script language.
 *
 * @param {string} script
 * @param {string} lang
 * @param {number} scriptOffset
 * @returns {BabelFile}
 */
function parseScript(script, lang, scriptOffset) {
    const plugins = [
        'importMeta',
    ];

    if (lang === 'ts' || lang === 'tsx') {
        plugins.push('typescript');
    }

    if (lang === 'jsx' || lang === 'tsx') {
        plugins.push('jsx');
    }

    try {
        return parse(script, {
            sourceType: 'module',
            plugins,
            errorRecovery: false,
            allowReturnOutsideFunction: false,
            ranges: true,
        });
    } catch (error) {
        const offset = typeof error.pos === 'number' ? scriptOffset + error.pos : scriptOffset;
        throw new ShopwareSetupTransformError(`Unable to parse Shopware setup script: ${error.message}`, offset);
    }
}

/**
 * Identifies scopes where `await` is no longer top-level for this transform.
 *
 * @param {BabelNode} node
 * @returns {boolean}
 */
function isFunctionNode(node) {
    return [
        'FunctionDeclaration',
        'FunctionExpression',
        'ArrowFunctionExpression',
        'ObjectMethod',
        'ClassMethod',
        'ClassPrivateMethod',
        'TSDeclareFunction',
    ].includes(node.type);
}

/**
 * Small AST walker used to avoid taking a heavier traversal dependency.
 *
 * @param {BabelNode | null | undefined} node
 * @param {(node: BabelNode, ancestors: BabelNode[]) => void} visitor
 * @param {BabelNode[]} [ancestors]
 * @returns {void}
 */
function walk(node, visitor, ancestors = []) {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    visitor(node, ancestors);

    Object.entries(node).forEach(
        ([
            key,
            value,
        ]) => {
            if (
                key === 'loc' ||
                key === 'range' ||
                key === 'leadingComments' ||
                key === 'trailingComments' ||
                key === 'innerComments'
            ) {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((child) => {
                    if (child && typeof child.type === 'string') {
                        walk(child, visitor, [
                            ...ancestors,
                            node,
                        ]);
                    }
                });
                return;
            }

            if (value && typeof value.type === 'string') {
                walk(value, visitor, [
                    ...ancestors,
                    node,
                ]);
            }
        },
    );
}

/**
 * Checks whether `inner` is fully covered by `outer`.
 *
 * @param {SourceRange} outer
 * @param {SourceRange} inner
 * @returns {boolean}
 */
function containsRange(outer, inner) {
    return outer.start <= inner.start && inner.end <= outer.end;
}

/**
 * Returns the expression Vue treats as the compiler macro call through transparent TypeScript wrappers.
 * Example: `defineProps<Props>() as Props` is collected as the inner `defineProps<Props>()` call while the
 * replacement range still preserves `as Props` around the generated setup input.
 *
 * @param {BabelNode | null | undefined} node
 * @returns {BabelNode | null | undefined}
 */
function unwrapTransparentMacroExpression(node) {
    if (
        node?.type === 'TSAsExpression' ||
        node?.type === 'TSSatisfiesExpression' ||
        node?.type === 'TSTypeAssertion' ||
        node?.type === 'TSNonNullExpression' ||
        node?.type === 'ParenthesizedExpression'
    ) {
        return unwrapTransparentMacroExpression(node.expression);
    }

    return node;
}

module.exports = {
    containsRange,
    getNodeRange,
    isFunctionNode,
    parseScript,
    unwrapTransparentMacroExpression,
    walk,
};
