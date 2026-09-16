/**
 * @sw-package framework
 */

const DEPRECATION_TEXT = /deprecat/i;
const LOGGING_METHODS = [
    'warn',
    'error',
];

/**
 * A logging call in any of the shapes the Administration uses: `console.warn(...)`,
 * `Shopware.Utils.debug.warn(...)`, and a `warn(...)` destructured from the debug utils.
 */
function isLoggingCall(node) {
    if (node.callee.type === 'Identifier') {
        return LOGGING_METHODS.includes(node.callee.name);
    }

    if (node.callee.type !== 'MemberExpression' || node.callee.computed) {
        return false;
    }

    return LOGGING_METHODS.includes(node.callee.property.name);
}

function initializerOf(sourceCode, node) {
    let scope = sourceCode.getScope(node);

    while (scope) {
        const variable = scope.set.get(node.name);
        const declaration = variable?.defs.find((definition) => definition.type === 'Variable');

        if (declaration) {
            return declaration.node.init;
        }

        scope = scope.upper;
    }

    return null;
}

/**
 * The parts of an argument that are known at lint time. A message is regularly assembled from
 * concatenated strings, a template literal, or an array of arguments spread into the call, so the
 * word that makes it a deprecation notice is rarely the whole first argument.
 */
function staticText(sourceCode, node, seen = new Set()) {
    if (!node) {
        return '';
    }

    switch (node.type) {
        case 'Literal':
            return typeof node.value === 'string' ? node.value : '';
        case 'TemplateLiteral':
            return node.quasis.map((quasi) => quasi.value.raw).join(' ');
        case 'BinaryExpression':
            return node.operator === '+'
                ? `${staticText(sourceCode, node.left, seen)} ${staticText(sourceCode, node.right, seen)}`
                : '';
        case 'ArrayExpression':
            return node.elements.map((element) => staticText(sourceCode, element, seen)).join(' ');
        case 'SpreadElement':
            return staticText(sourceCode, node.argument, seen);
        case 'ConditionalExpression':
            return `${staticText(sourceCode, node.consequent, seen)} ${staticText(sourceCode, node.alternate, seen)}`;
        case 'Identifier':
            if (seen.has(node.name)) {
                return '';
            }

            seen.add(node.name);

            return staticText(sourceCode, initializerOf(sourceCode, node), seen);
        default:
            return '';
    }
}

/**
 * A deprecation notice belongs in the feature lifecycle, not in a `console.warn`. A hand-rolled notice
 * never throws once the major flag is active, so the use it reports survives into the major and is
 * only found after the removal.
 *
 * Replace it with the guard:
 *
 *     Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'x is deprecated. Use y instead.');
 *
 * A notice whose removal version is genuinely unknown has no flag to guard with. Disable the rule on
 * that line and say why:
 *
 *     // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- <reason>
 *
 * See adr/2026-08-10-administration-javascript-deprecation-guards.md.
 */
/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',

        docs: {
            description: 'Deprecation notices go through the feature lifecycle, not through console',
            recommended: true,
            url: 'https://github.com/shopware/shopware/blob/trunk/adr/2026-08-10-administration-javascript-deprecation-guards.md',
        },

        schema: [],

        messages: {
            manualNotice:
                'Emit this deprecation through Shopware.Feature.triggerDeprecationOrThrow(majorFlag, message) ' +
                'so it throws once the major flag is active. If the removal version is unknown, disable this ' +
                'rule on the line and state why.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode ?? context.getSourceCode();

        return {
            CallExpression(node) {
                if (!isLoggingCall(node)) {
                    return;
                }

                const message = node.arguments.map((argument) => staticText(sourceCode, argument)).join(' ');

                if (!DEPRECATION_TEXT.test(message)) {
                    return;
                }

                context.report({ node, messageId: 'manualNotice' });
            },
        };
    },
};
