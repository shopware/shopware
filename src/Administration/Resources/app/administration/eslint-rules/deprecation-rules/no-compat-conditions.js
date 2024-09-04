module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Remove compat mode conditions and keep non-compat version',
            category: 'Best Practices',
            recommended: false,
        },
        schema: [
            {
                enum: ['disableFix', 'enableFix'],
            }
        ],
        fixable: 'code',
    },
    create(context) {
        const sourceCode = context.getSourceCode();

        function removeExtraBraces(text) {
            return text.replace(/^\s*{\s*([\s\S]*?)\s*}\s*$/, '$1');
        }

        function adjustIndentation(text, baseIndent) {
            const lines = text.split('\n');
            return lines.map((line, index) => {
                if (index === 0) return line.trim();
                return baseIndent + line.trim();
            }).join('\n');
        }

        function isCompatEnabledCall(node) {
            if (node.type === 'UnaryExpression' && node.operator === '!') {
                node = node.argument;
            }
            return (
                (
                    node.type === 'CallExpression' &&
                    (
                        (
                            node.callee.type === 'MemberExpression' &&
                            node.callee.object.type === 'ThisExpression' &&
                            node.callee.property.name === 'isCompatEnabled') ||
                        (
                            node.callee.type === 'MemberExpression' &&
                            node.callee.object.name === 'compatUtils' &&
                            node.callee.property.name === 'isCompatEnabled'
                        )
                    )
                )
            );
        }

        return {
            IfStatement(node) {
                if (isCompatEnabledCall(node.test)) {
                    context.report({
                        node,
                        message: 'Feature flag condition should be removed',
                        fix(fixer) {
                            if (context.options.includes('disableFix')) return;

                            let replacementNode;
                            if (node.test.type === 'UnaryExpression' && node.test.operator === '!') {
                                replacementNode = node.consequent;
                            } else {
                                replacementNode = node.alternate;
                            }

                            if (replacementNode) {
                                const replacementText = sourceCode.getText(replacementNode);
                                const baseIndent = sourceCode.getText(node).match(/^\s*/)[0];
                                const adjustedText = adjustIndentation(removeExtraBraces(replacementText), baseIndent);
                                return fixer.replaceText(node, adjustedText);
                            } else {
                                return fixer.remove(node);
                            }
                        },
                    });
                }
            },
            ConditionalExpression(node) {
                if (isCompatEnabledCall(node.test)) {
                    context.report({
                        node,
                        message: 'Feature flag condition should be removed',
                        fix(fixer) {
                            if (context.options.includes('disableFix')) return;
                            return fixer.replaceText(node, sourceCode.getText(node.alternate));
                        },
                    });
                }
            },
        };
    },
};
