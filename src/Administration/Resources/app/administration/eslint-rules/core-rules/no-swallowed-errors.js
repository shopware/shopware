/**
 * @sw-package framework
 */

const CONSTANT_TYPES = new Set([
    'Literal',
    'Identifier',
    'ArrayExpression',
    'ObjectExpression',
]);

function isConstant(node) {
    return !node || CONSTANT_TYPES.has(node.type);
}

// The handler neither looks at the error nor does anything else: `() => {}`, `() => null` or `() => { return; }`.
function dropsError(handler) {
    const { body } = handler;

    if (body.type !== 'BlockStatement') {
        return isConstant(body);
    }

    if (body.body.length === 0) {
        return true;
    }

    const [statement] = body.body;

    return body.body.length === 1 && statement.type === 'ReturnStatement' && isConstant(statement.argument);
}

function usesParameter(context, handler) {
    return context.sourceCode.getDeclaredVariables(handler).some((variable) => variable.references.length > 0);
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow .catch() handlers that drop the error without saying why',
        },
        schema: [],
        messages: {
            swallowedError:
                'This .catch() handler drops the error. Handle it, rethrow it, ' +
                'or add a comment that says why ignoring it is safe.',
        },
    },

    create(context) {
        return {
            "CallExpression[callee.type='MemberExpression'][callee.computed=false][callee.property.name='catch']"(node) {
                const [handler] = node.arguments;

                if (!handler || !['ArrowFunctionExpression', 'FunctionExpression'].includes(handler.type)) {
                    return;
                }

                if (!dropsError(handler) || usesParameter(context, handler)) {
                    return;
                }

                if (context.sourceCode.getCommentsInside(handler).length > 0) {
                    return;
                }

                context.report({ node: handler, messageId: 'swallowedError' });
            },
        };
    },
};
