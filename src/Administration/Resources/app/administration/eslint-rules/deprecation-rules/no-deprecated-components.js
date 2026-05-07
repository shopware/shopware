/**
 * @sw-package framework
 *
 * This rule checks if deprecated components are used and can convert them to the new components.
 * It also adds a comment to the converted component to make it easier to track the changes.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
function getDirectiveName(attribute) {
    return attribute.key?.name?.name ?? attribute.key?.name;
}

function getDirectiveExpression(node, directiveName) {
    const attribute = node?.startTag?.attributes?.find((candidate) => {
        return getDirectiveName(candidate) === directiveName;
    });

    return attribute?.value?.expression;
}

function isMajorFeatureFlagCall(expression) {
    return expression?.type === 'CallExpression' &&
        expression.callee?.type === 'MemberExpression' &&
        expression.callee.property?.name === 'isActive' &&
        expression.callee.object?.type === 'MemberExpression' &&
        expression.callee.object.property?.name === 'Feature' &&
        expression.callee.object.object?.name === 'Shopware' &&
        expression.arguments?.[0]?.value === 'V6_8_0_0';
}

function isPositiveMajorFeatureFlagExpression(expression) {
    return isMajorFeatureFlagCall(expression);
}

function isNegatedMajorFeatureFlagExpression(expression) {
    return expression?.type === 'UnaryExpression' &&
        expression.operator === '!' &&
        isMajorFeatureFlagCall(expression.argument);
}

function isInactiveMajorCompatibilityBranch(node) {
    let currentNode = node.parent;

    while (currentNode) {
        const elseIfExpression = getDirectiveExpression(currentNode, 'else-if');

        if (isNegatedMajorFeatureFlagExpression(elseIfExpression)) {
            return true;
        }

        const hasElse = currentNode.startTag?.attributes?.some((attribute) => {
            return getDirectiveName(attribute) === 'else';
        });

        if (hasElse) {
            const siblings = currentNode.parent?.children ?? [];
            const currentIndex = siblings.indexOf(currentNode);
            const previousElement = siblings.slice(0, currentIndex).reverse().find((sibling) => {
                return sibling.type === 'VElement';
            });

            const previousIfExpression = getDirectiveExpression(previousElement, 'if');

            if (isPositiveMajorFeatureFlagExpression(previousIfExpression)) {
                return true;
            }
        }

        currentNode = currentNode.parent;
    }

    return false;
}

module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'No usage of deprecated components',
            recommended: true,
        },
        schema: [
            {
                type: 'object',
                properties: {
                    fix: {
                        type: 'boolean',
                    },
                    activatedComponents: {
                        type: 'array',
                        items: {
                            type: 'string'
                        }
                    }
                }
            }
        ]
    },
    /** @param {RuleContext} context */
    create(context) {
        return context.sourceCode.parserServices.defineTemplateBodyVisitor(
            // Event handlers for <template> tags
            {
                VElement(node) {
                    const enableFix = context.options?.[0]?.fix ?? true;
                    const activatedComponents = context.options?.[0]?.activatedComponents ?? [
                        'sw-button',
                        'sw-icon',
                        'sw-colorpicker',
                        'sw-card',
                        'sw-text-field',
                        'sw-number-field',
                        'sw-external-link',
                        'sw-url-field',
                        'sw-loader',
                        'sw-datepicker',
                        'sw-skeleton-bar',
                        'sw-email-field',
                        'sw-password-field',
                        'sw-progress-bar',
                        'sw-switch-field',
                        'sw-checkbox-field',
                        'sw-textarea-field',
                        'sw-select-field',
                        'sw-alert',
                        'sw-popover',
                        'sw-data-grid'
                    ];

                    const conversionMap = [
                        {
                            before: 'sw-switch-field',
                            after: 'mt-switch'
                        },
                        {
                            before: 'sw-checkbox-field',
                            after: 'mt-checkbox'
                        },
                        {
                            before: 'sw-textarea-field',
                            after: 'mt-textarea'
                        },
                        {
                            before: 'sw-select-field',
                            after: 'mt-select'
                        },
                        {
                            before: 'sw-alert',
                            after: 'mt-banner'
                        },
                        {
                            before: 'sw-popover',
                            after: 'mt-floating-ui'
                        },
                    ].filter(conversion => activatedComponents.includes(conversion.before));

                    const deprecatedTabComponents = ['sw-tabs', 'sw-tabs-item'];

                    if (deprecatedTabComponents.includes(node.name)) {
                        if (isInactiveMajorCompatibilityBranch(node)) {
                            return;
                        }

                        context.report({
                            loc: node.loc,
                            message: `"${node.name}" is deprecated. Please use "mt-tabs" with the "items" property instead.`,
                        });

                        return;
                    }

                    // Handle deprecated components
                    conversionMap.forEach(conversion => {
                        if (node.name === conversion.before) {
                            const componentName = conversion.before;
                            const newComponentName = conversion.after;

                            // Convert old component to new component
                            context.report({
                                loc: node.loc,
                                message: `"${componentName}" is deprecated. Please use "${newComponentName}" instead.`,
                                *fix(fixer) {
                                    if (!enableFix) return;

                                    const isSelfClosing = node.startTag.selfClosing;

                                    // Handle self-closing tags
                                    if (isSelfClosing) {
                                        // Replace the component name
                                        const startTagRange = [node.startTag.range[0], componentName.length + node.startTag.range[0] + 1];
                                        yield fixer.replaceTextRange(startTagRange, `<${newComponentName}`);

                                        // Save indentation of the old component
                                        const indentation = node.loc.start.column;

                                        // Add comment to the converted component
                                        yield fixer.insertTextBeforeRange(startTagRange, `<!-- TODO Codemod: Converted from ${componentName} - please check if everything works correctly -->\n${' '.repeat(indentation)}`);

                                        return;
                                    }

                                    // Handle non-self-closing tags
                                    const startTagRange = [node.startTag.range[0], componentName.length + node.startTag.range[0] + 1];
                                    const endTagRange = node.endTag.range;

                                    // Replace the component name
                                    yield fixer.replaceTextRange(startTagRange, `<${newComponentName}`);
                                    yield fixer.replaceTextRange(endTagRange, `</${newComponentName}>`);

                                    // Save indentation of the old component
                                    const indentation = node.loc.start.column;

                                    // Add comment to the converted component
                                    yield fixer.insertTextBeforeRange(startTagRange, `<!-- TODO Codemod: Converted from ${componentName} - please check if everything works correctly -->\n${' '.repeat(indentation)}`);
                                }
                            });
                        }
                    });

                    const deprecatedComponents = [
                        'sw-button',
                        'sw-icon',
                        'sw-colorpicker',
                        'sw-card',
                        'sw-text-field',
                        'sw-number-field',
                        'sw-external-link',
                        'sw-url-field',
                        'sw-loader',
                        'sw-datepicker',
                        'sw-skeleton-bar',
                        'sw-email-field',
                        'sw-password-field',
                        'sw-progress-bar'
                    ].filter(component => activatedComponents.includes(component));

                    // Handle other deprecated components
                    if (deprecatedComponents.includes(node.name)) {
                        const componentName = node.name;
                        const newComponentName = componentName.replace('sw-', 'mt-');

                        // Convert old component to new component
                        context.report({
                            loc: node.loc,
                            message: `"${componentName}" is deprecated. Please use "${newComponentName}" instead.`,
                            *fix(fixer) {
                                if (!enableFix) return;

                                const isSelfClosing = node.startTag.selfClosing;

                                // Handle self-closing tags
                                if (isSelfClosing) {
                                    // Replace the component name
                                    const startTagRange = [node.startTag.range[0], componentName.length + node.startTag.range[0] + 1];
                                    yield fixer.replaceTextRange(startTagRange, `<${newComponentName}`);

                                    // Save indentation of the old component
                                    const indentation = node.loc.start.column;

                                    // Add comment to the converted component
                                    yield fixer.insertTextBeforeRange(startTagRange, `<!-- TODO Codemod: Converted from ${componentName} - please check if everything works correctly -->\n${' '.repeat(indentation)}`);

                                    return;
                                }

                                // Handle non-self-closing tags
                                const startTagRange = [node.startTag.range[0], componentName.length + node.startTag.range[0] + 1];
                                const endTagRange = node.endTag.range;

                                // Replace the component name
                                yield fixer.replaceTextRange(startTagRange, `<${newComponentName}`);
                                yield fixer.replaceTextRange(endTagRange, `</${newComponentName}>`);

                                // Save indentation of the old component
                                const indentation = node.loc.start.column;

                                // Add comment to the converted component
                                yield fixer.insertTextBeforeRange(startTagRange, `<!-- TODO Codemod: Converted from ${componentName} - please check if everything works correctly -->\n${' '.repeat(indentation)}`);
                            }
                        });
                    }

                    // Handle special sw-data-grid component
                    const swDatagridName = 'sw-data-grid';
                    if (node.name === swDatagridName && activatedComponents.includes(swDatagridName)) {
                        // Check if comment a line before the sw-data-grid component exists
                        const commentBeforeNode = context.getSourceCode().getText().split('\n')[node.loc.start.line - 2];

                        // Do not add comment if it already exists
                        if (commentBeforeNode.includes('<!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->')) {
                            return;
                        }

                        // Add comment a line before the sw-data-grid component
                        context.report({
                            loc: node.loc,
                            message: `"${swDatagridName}" is deprecated. Please use "mt-data-table" instead.`,
                            *fix(fixer) {
                                if (!enableFix) return;

                                // Get the range of the start tag
                                const startTagRange = [node.startTag.range[0], swDatagridName.length + node.startTag.range[0] + 1];

                                // Save indentation of the old component
                                const indentation = node.loc.start.column;

                                // Add comment to the converted component
                                yield fixer.insertTextBeforeRange(startTagRange, `<!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->\n${' '.repeat(indentation)}`);
                            }
                        });
                    }
                },
            }
        )
    }
};
