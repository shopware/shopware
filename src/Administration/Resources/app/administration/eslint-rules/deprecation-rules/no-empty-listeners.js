const fs = require('fs');
const path = require('path');

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Remove empty listeners() methods',
            category: 'Vue 3 Migration',
            recommended: true,
        },
        fixable: 'code',
        schema: [
            {
                enum: ['disableFix', 'enableFix'],
            },
        ],
    },
    create(context) {
        let hasEmptyListeners = false;
        let templatePath = null;

        return {
            ImportDeclaration(node) {
                if (node.specifiers.some(specifier =>
                    specifier.type === 'ImportDefaultSpecifier' && specifier.local.name === 'template')) {
                    templatePath = node.source.value;
                }
            },
            'Property[key.name="listeners"][value.type=/FunctionExpression|ArrowFunctionExpression/]'(node) {
                const body = node.value.body;
                const isEmptyObject = (node) =>
                    node.type === 'ObjectExpression' && node.properties.length === 0;

                if (
                    (body.type === 'BlockStatement' &&
                        body.body.length === 1 &&
                        body.body[0].type === 'ReturnStatement' &&
                        isEmptyObject(body.body[0].argument)) ||
                    isEmptyObject(body)
                ) {
                    hasEmptyListeners = true;

                    context.report({
                        node,
                        message: 'Empty listeners() method should be removed for Vue 3 migration',
                        fix(fixer) {
                            if (context.options.includes('disableFix')) return;

                            const sourceCode = context.getSourceCode();
                            const parentNode = node.parent;

                            if (parentNode.properties.length === 1) {
                                // If it's the only property in object, replace object with an empty object
                                return fixer.replaceText(parentNode, '{}');
                            } else {
                                const tokens = sourceCode.getTokens(node);
                                const prevToken = sourceCode.getTokenBefore(node);
                                const nextToken = sourceCode.getTokenAfter(node);

                                if (nextToken && nextToken.value === ',') {
                                    const start = sourceCode.getTokenBefore(node).range[1];
                                    const end = sourceCode.getTokenAfter(node).range[1];

                                    // If there's a comma after, remove it too and adjust whitespace
                                    return fixer.replaceTextRange([start, end], '');
                                }
                                else if (prevToken && prevToken.value === ',') {
                                    const start = sourceCode.getTokenBefore(prevToken).range[1];
                                    const end = sourceCode.getTokenAfter(node).range[0];
                                    const previousWhitespace = sourceCode.getTokenAfter(node).loc.start.column;

                                    // If there's a comma before, remove it too and adjust whitespace
                                    return fixer.replaceTextRange([start, end], '\n' + ' '.repeat(previousWhitespace));
                                }
                                else {
                                    // Otherwise, just remove the node and adjust whitespace
                                    const start = sourceCode.getTokenBefore(node).range[1];
                                    const end = sourceCode.getTokenAfter(node).range[0];
                                    return fixer.replaceTextRange([start, end], '');
                                }
                            }
                        },
                    });
                }
            },
            'Program:exit'() {
                if (hasEmptyListeners && templatePath) {
                    const currentFilePath = context.getFilename();
                    const currentDir = path.dirname(currentFilePath);
                    const fullTemplatePath = path.resolve(currentDir, templatePath);

                    if (fs.existsSync(fullTemplatePath)) {
                        const templateContent = fs.readFileSync(fullTemplatePath, 'utf-8');
                        const updatedContent = templateContent.replace(/\s*v-on="listeners"\s*/g, ' ');

                        if (templateContent !== updatedContent) {
                            context.report({
                                loc: { line: 1, column: 0 },
                                message: `Remove v-on="listeners" from ${templatePath}`,
                                fix(fixer) {
                                    if (context.options.includes('disableFix')) return;

                                    return fixer.insertAfterRange?.([0, 0], '');
                                },
                            });

                            if (!context.options.includes('disableFix')) {
                                fs.writeFileSync(fullTemplatePath, updatedContent);
                            }
                        }
                    }
                }
            },
        };
    },
};
