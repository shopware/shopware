/**
 * @sw-package framework
 */

const { isTestDeclaration } = require('./test-declaration');

/** The spec files of a mini-module that test its public API. */
const CONTRACT_TESTS = /(^|\/)(requirements|pinned|regressions)\.spec\.[jt]s$/;

const STEPS = ['ARRANGE', 'ACT', 'ASSERT'];

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: "Tests in a mini-module's contract specs mark their steps with // ARRANGE, // ACT and // ASSERT",
        },
        messages: {
            missingStep: "Mark where the {{purpose}} starts with '// {{step}}'.",
            missingArrange: "Code runs before '// ACT', so mark where it starts with '// ARRANGE'.",
            wrongOrder: "Write '// ARRANGE', '// ACT' and '// ASSERT' in this order.",
        },
        schema: [],
    },
    create(context) {
        const filename = (context.filename ?? context.getFilename()).split('\\').join('/');

        if (!CONTRACT_TESTS.test(filename)) {
            return {};
        }

        const sourceCode = context.sourceCode ?? context.getSourceCode();

        return {
            CallExpression(node) {
                if (!isTestDeclaration(node) || node.parent.type !== 'ExpressionStatement') {
                    return;
                }

                const callback = node.arguments.at(-1);
                if (!callback || !['ArrowFunctionExpression', 'FunctionExpression'].includes(callback.type)) {
                    return;
                }
                if (callback.body.type !== 'BlockStatement') {
                    return;
                }

                const steps = sourceCode
                    .getCommentsInside(callback.body)
                    .filter((comment) => comment.type === 'Line' && STEPS.includes(comment.value.trim()))
                    .map((comment) => ({ step: comment.value.trim(), line: comment.loc.start.line }));
                const lineOf = (step) => steps.find((found) => found.step === step)?.line;

                if (lineOf('ACT') === undefined) {
                    context.report({
                        node,
                        messageId: 'missingStep',
                        data: { step: 'ACT', purpose: 'call of the code under test' },
                    });
                    return;
                }
                if (lineOf('ASSERT') === undefined) {
                    context.report({ node, messageId: 'missingStep', data: { step: 'ASSERT', purpose: 'checks' } });
                    return;
                }

                const order = steps.map(({ step }) => STEPS.indexOf(step));
                if (order.some((index, position) => position > 0 && index <= order[position - 1])) {
                    context.report({ node, messageId: 'wrongOrder' });
                    return;
                }

                const codeBeforeAct = callback.body.body.some((statement) => statement.loc.start.line < lineOf('ACT'));
                if (codeBeforeAct && lineOf('ARRANGE') === undefined) {
                    context.report({ node, messageId: 'missingArrange' });
                }
            },
        };
    },
};
