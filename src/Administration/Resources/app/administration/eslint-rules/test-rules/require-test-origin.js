/**
 * @sw-package framework
 */

const { isTestDeclaration } = require('./test-declaration');

/**
 * The doc tag a test needs in each kind of spec file.
 * Pinned specs need none: they freeze today's behaviour, which nobody decided.
 * A public-api test names the member it pins, because changing that behaviour can break extensions.
 * A private-api test names the member it pins too, so a reader of the nested module sees what its parent relies on.
 */
const REQUIRED_TAGS = [
    {
        file: /(^|\/)pinned\.spec(\/[^/]+\.spec)?\.[jt]s$/,
        tag: null,
    },
    {
        file: /(^|\/)public-api\.spec(\/[^/]+\.spec)?\.[jt]s$/,
        tag: '@public-api',
        pattern: /@public-api\s+\S/,
        hint: 'the public member it pins; a change to it can break extensions',
    },
    {
        file: /(^|\/)private-api\.spec(\/[^/]+\.spec)?\.[jt]s$/,
        tag: '@private-api',
        pattern: /@private-api\s+\S/,
        hint: 'the member of private.ts it pins',
    },
    {
        file: /(^|\/)requirements\.spec(\/[^/]+\.spec)?\.[jt]s$/,
        tag: '@requirement',
        pattern: /@requirement\s+\S/,
        hint: 'what a user expects and why',
    },
    {
        file: /(^|\/)regressions\.spec(\/[^/]+\.spec)?\.[jt]s$/,
        tag: '@regression',
        pattern: /@regression\s+(#\d+|https?:\/\/\S+)/,
        hint: 'the issue (#1234 or a link) and what users saw',
    },
];

/**
 * Returns true for a test that the next major removes: `it.deprecated('v6.8.0.0')(...)`.
 *
 * @param {import('estree').CallExpression} node
 * @returns {boolean}
 */
function isDeprecatedTest(node) {
    const callee = node.callee;

    return (
        callee.type === 'CallExpression' &&
        callee.callee.type === 'MemberExpression' &&
        callee.callee.property.name === 'deprecated'
    );
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description:
                'Tests in requirements and regressions specs document where they come from, deprecated tests when they go',
        },
        messages: {
            missingOrigin: 'Add a doc comment with {{tag}} to this test: {{hint}}.',
            missingDeprecation:
                "Add '@deprecated tag:vX.Y.Z - <reason>' to the doc comment of this test, so it is found when the major removes it.",
        },
        schema: [],
    },
    create(context) {
        const filename = (context.filename ?? context.getFilename()).split('\\').join('/');
        const required = REQUIRED_TAGS.find(({ file }) => file.test(filename));

        if (!required) {
            return {};
        }

        const sourceCode = context.sourceCode ?? context.getSourceCode();

        return {
            CallExpression(node) {
                if (!isTestDeclaration(node) || node.parent.type !== 'ExpressionStatement') {
                    return;
                }

                const docComments = sourceCode
                    .getCommentsBefore(node.parent)
                    .filter((comment) => comment.type === 'Block' && comment.value.startsWith('*'))
                    .map((comment) => comment.value);

                if (required.tag && !docComments.some((comment) => required.pattern.test(comment))) {
                    context.report({ node, messageId: 'missingOrigin', data: { tag: required.tag, hint: required.hint } });
                }

                if (isDeprecatedTest(node) && !docComments.some((comment) => /@deprecated tag:v\d/.test(comment))) {
                    context.report({ node, messageId: 'missingDeprecation' });
                }
            },
        };
    },
};
