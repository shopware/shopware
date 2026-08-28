/**
 * @sw-package framework
 */

const FEATURE_FLAG = 'v6.8.0.0';

function getDirective(node, name) {
    return node.startTag?.attributes.find((attribute) => {
        return attribute.directive === true && attribute.key?.name?.name === name;
    });
}

function isFeatureActiveCall(expression) {
    if (expression?.type !== 'CallExpression') {
        return false;
    }

    const callee = expression.callee;

    return callee?.type === 'MemberExpression'
        && callee.object?.type === 'Identifier'
        && callee.object.name === 'feature'
        && callee.property?.type === 'Identifier'
        && callee.property.name === 'isActive'
        && callee.computed === false
        && expression.arguments.length === 1
        && expression.arguments[0]?.type === 'Literal'
        && expression.arguments[0].value === FEATURE_FLAG;
}

function isPositiveFeatureCondition(expression) {
    if (isFeatureActiveCall(expression)) {
        return true;
    }

    if (expression?.type === 'LogicalExpression') {
        return isPositiveFeatureCondition(expression.left) || isPositiveFeatureCondition(expression.right);
    }

    return false;
}

function isNegativeFeatureCondition(expression) {
    return expression?.type === 'UnaryExpression'
        && expression.operator === '!'
        && isFeatureActiveCall(expression.argument);
}

function hasPositiveFeatureIf(node) {
    const vIf = getDirective(node, 'if') ?? getDirective(node, 'else-if');

    return isPositiveFeatureCondition(vIf?.value?.expression);
}

function hasNegativeFeatureIf(node) {
    const vIf = getDirective(node, 'if');

    return isNegativeFeatureCondition(vIf?.value?.expression);
}

function getPreviousNonEmptySiblingElement(node) {
    const siblings = node.parent?.children;

    if (!Array.isArray(siblings)) {
        return null;
    }

    const index = siblings.indexOf(node);

    for (let i = index - 1; i >= 0; i -= 1) {
        const sibling = siblings[i];

        if (sibling.type === 'VText' && sibling.value.trim() === '') {
            continue;
        }

        if (sibling.type === 'VElement') {
            return sibling;
        }

        return null;
    }

    return null;
}

function isMatchingFeatureElse(node) {
    if (!getDirective(node, 'else')) {
        return false;
    }

    const previousSibling = getPreviousNonEmptySiblingElement(node);

    return previousSibling?.name === 'template' && hasPositiveFeatureIf(previousSibling);
}

function isAllowedFallbackBranch(node) {
    return node.type === 'VElement'
        && (
            hasNegativeFeatureIf(node)
            || isMatchingFeatureElse(node)
        );
}

function isInsideAllowedFallbackBranch(node) {
    let parent = node;

    while (parent) {
        if (isAllowedFallbackBranch(parent)) {
            return true;
        }

        parent = parent.parent;
    }

    return false;
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Disallow unguarded sw-tabs usage',
            recommended: true,
        },
        messages: {
            noSwTabsUsage: `"sw-tabs" may only be used as the legacy fallback for the "${FEATURE_FLAG}" feature flag. Use "mt-tabs" for new code.`,
        },
        schema: [],
    },

    create(context) {
        const templateVisitor = {
            VElement(node) {
                if (node.name !== 'sw-tabs') {
                    return;
                }

                if (isInsideAllowedFallbackBranch(node)) {
                    return;
                }

                context.report({
                    node,
                    messageId: 'noSwTabsUsage',
                });
            },
        };

        return context.sourceCode.parserServices.defineTemplateBodyVisitor(templateVisitor);
    },
};
