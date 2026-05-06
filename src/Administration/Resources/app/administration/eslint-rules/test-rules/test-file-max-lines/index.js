/**
 * @sw-package framework
 */

const DEFAULT_MAX_LINES = 500;

const createRule = (defaultMaxLines = DEFAULT_MAX_LINES) => {
    return {
        meta: {
            type: 'suggestion',
            schema: [
                {
                    type: 'object',
                    properties: {
                        max: {
                            type: 'number',
                            minimum: 1,
                        },
                    },
                    additionalProperties: false,
                },
            ],
        },

        create(context) {
            const maxLines = context.options?.[0]?.max ?? defaultMaxLines;
            const sourceCode = context.getSourceCode();
            const lineCount = sourceCode.lines.length;

            if (lineCount < maxLines) {
                return {};
            }

            return {
                Program(node) {
                    context.report({
                        node,
                        message: `Test file has ${lineCount} lines. Split test files with ${maxLines} lines or more into smaller specs.`,
                    });
                },
            };
        },
    };
};

module.exports = {
    createRule,
};
