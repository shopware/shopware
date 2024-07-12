const fs = require('fs');
const path = require('path');

/* eslint-disable max-len */

/**
 * @package admin
 *
 * This rule checks if deprecated components are used and can convert them to the new components.
 * It also adds a comment to the converted component to make it easier to track the changes.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
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
                enum: ['disableFix', 'enableFix'],
            }
        ]
    },
    /** @param {RuleContext} context */
    create(context) {
        return context.parserServices.defineTemplateBodyVisitor(
            // Event handlers for <template> tags
            {
                VElement(node) {
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
                    ]

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
                                    if (context.options.includes('disableFix')) return;

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
                        'sw-tabs',
                        'sw-datepicker',
                        'sw-skeleton-bar',
                        'sw-email-field',
                        'sw-tabs',
                        'sw-password-field',
                        'sw-progress-bar'
                    ];

                    // Handle other deprecated components
                    if (deprecatedComponents.includes(node.name)) {
                        const componentName = node.name;
                        const newComponentName = componentName.replace('sw-', 'mt-');

                        // Convert old component to new component
                        context.report({
                            loc: node.loc,
                            message: `"${componentName}" is deprecated. Please use "${newComponentName}" instead.`,
                            *fix(fixer) {
                                if (context.options.includes('disableFix')) return;

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
                    if (node.name === swDatagridName) {
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
                                if (context.options.includes('disableFix')) return;

                                const isSelfClosing = node.startTag.selfClosing;

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
