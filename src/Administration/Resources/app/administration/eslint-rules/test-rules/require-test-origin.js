/**
 * @sw-package framework
 */

/**
 * The doc tag a test needs in each kind of spec file.
 * Pinned specs need none, because their file name already says that nobody decided the behaviour.
 */
const REQUIRED_TAGS = [
    {
        file: /(^|\/)requirements\.spec\.[jt]s$/,
        tag: '@requirement',
        pattern: /@requirement\s+\S/,
        hint: 'what a user expects and why',
    },
    {
        file: /(^|\/)regressions\.spec\.[jt]s$/,
        tag: '@regression',
        pattern: /@regression\s+(#\d+|https?:\/\/\S+)/,
        hint: 'the issue (#1234 or a link) and what users saw',
    },
];

/**
 * Returns true for the call that declares a test: `it(...)`, `it.only(...)`
 * and the curried helpers such as `it.deprecated('v6.8.0.0')(...)`.
 *
 * @param {import('estree').CallExpression} node
 * @returns {boolean}
 */
function isTestDeclaration(node) {
    const callee = node.callee;

    if (callee.type === 'Identifier') {
        return callee.name === 'it';
    }

    if (callee.type === 'MemberExpression') {
        return callee.object.type === 'Identifier' && callee.object.name === 'it' && node.parent.type !== 'CallExpression';
    }

    return (
        callee.type === 'CallExpression' && callee.callee.type === 'MemberExpression' && callee.callee.object.name === 'it'
    );
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Tests in requirements and regressions specs document where they come from',
        },
        messages: {
            missingOrigin: 'Add a doc comment with {{tag}} to this test: {{hint}}.',
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

                const documented = sourceCode
                    .getCommentsBefore(node.parent)
                    .some(
                        (comment) =>
                            comment.type === 'Block' &&
                            comment.value.startsWith('*') &&
                            required.pattern.test(comment.value),
                    );

                if (!documented) {
                    context.report({ node, messageId: 'missingOrigin', data: { tag: required.tag, hint: required.hint } });
                }
            },
        };
    },
};
