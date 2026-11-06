/**
 * @sw-package framework
 */

const { collectTestCases } = require('./test-cases');

/**
 * Reports every `@seeException`, so a source outside the allowlist stays visible in each lint run.
 * Runs as a warning next to `test-annotation`, which checks that the reason is long enough.
 */
module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Keeps every @seeException of a test visible as a warning.',
        },
        schema: [],
        messages: {
            seeException:
                'This test cites a source outside the allowlist: {{ reason }}. If the source is common, add it to the allowlist in eslint-rules/test-rules/test-annotation/sources.js.',
        },
    },

    create(context) {
        const sourceCode = context.sourceCode;

        return {
            Program(program) {
                const { tests, describes } = collectTestCases(program, sourceCode.text, sourceCode.getAllComments());

                [
                    ...describes,
                    ...tests,
                ].forEach(({ node, doc }) => {
                    (doc?.tags ?? [])
                        .filter((tag) => tag.name === 'seeException')
                        .forEach((tag) => {
                            context.report({ node, messageId: 'seeException', data: { reason: tag.value } });
                        });
                });
            },
        };
    },
};
