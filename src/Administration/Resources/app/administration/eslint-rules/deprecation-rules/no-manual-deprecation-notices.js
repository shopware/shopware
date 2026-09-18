/**
 * @sw-package framework
 */

const DEPRECATION_NOTICE_PATTERN = /\bdeprecated\b|\[Shopware Deprecation]|\[Deprecation]|Deprecation Warning/i;

function isLoggingCall(node) {
    if (node.callee.type === 'Identifier') {
        return node.callee.name === 'warn' || node.callee.name === 'error';
    }

    if (node.callee.type !== 'MemberExpression') {
        return false;
    }

    const propertyName = node.callee.computed ? node.callee.property.value : node.callee.property.name;

    return propertyName === 'warn' || propertyName === 'error';
}

function findVariable(scope, name) {
    while (scope) {
        const variable = scope.set.get(name);

        if (variable) {
            return variable;
        }

        scope = scope.upper;
    }

    return null;
}

function getStaticText(node, sourceCode, visitedVariables = new Set()) {
    if (!node) {
        return '';
    }

    if (node.type === 'Literal') {
        return typeof node.value === 'string' ? node.value : '';
    }

    if (node.type === 'TemplateLiteral') {
        return node.quasis.map((quasi) => quasi.value.raw).join('');
    }

    if (node.type === 'BinaryExpression' && node.operator === '+') {
        return (
            getStaticText(node.left, sourceCode, visitedVariables) + getStaticText(node.right, sourceCode, visitedVariables)
        );
    }

    if (node.type === 'ArrayExpression') {
        return node.elements.map((element) => getStaticText(element, sourceCode, visitedVariables)).join(' ');
    }

    if (node.type === 'SpreadElement') {
        return getStaticText(node.argument, sourceCode, visitedVariables);
    }

    if (node.type !== 'Identifier' || visitedVariables.has(node.name)) {
        return '';
    }

    const variable = findVariable(sourceCode.getScope(node), node.name);
    const definition = variable?.defs.find((candidate) => candidate.type === 'Variable');

    if (!definition?.node.init) {
        return '';
    }

    visitedVariables.add(node.name);

    return getStaticText(definition.node.init, sourceCode, visitedVariables);
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Require feature-aware Administration deprecation notices',
            recommended: true,
        },
        schema: [],
        messages: {
            manualNotice:
                'Use Shopware.Feature.triggerDeprecationOrThrow() instead of emitting a deprecation notice manually.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode;

        return {
            CallExpression(node) {
                if (!isLoggingCall(node)) {
                    return;
                }

                const message = node.arguments.map((argument) => getStaticText(argument, sourceCode)).join(' ');

                if (!DEPRECATION_NOTICE_PATTERN.test(message)) {
                    return;
                }

                context.report({
                    node,
                    messageId: 'manualNotice',
                });
            },
        };
    },
};
