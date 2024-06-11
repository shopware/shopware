/**
 * @package admin
 */
/* eslint-disable max-len */
module.exports = {
    meta: {
        type: 'layout',

        docs: {
            description: 'Each file should have a package annotation',
            recommended: true,
        },
    },
    create(context) {
        const sourceCode = context.getSourceCode();
        const comments = sourceCode.getAllComments();

        // Check if the file is a js, ts, spec.js or spec.ts file
        const isJsFile = context.getFilename().endsWith('.js');
        const isTsFile = context.getFilename().endsWith('.ts');
        const isSpecFile = context.getFilename().endsWith('.spec.js') || context.getFilename().endsWith('.spec.ts');

        // Skip if it's a spec file or not a js/ts file
        if (isSpecFile || (!isJsFile && !isTsFile)) {
            return {};
        }

        // Check every comment in the file
        let hasPackageAnnotation = false;

        for (const comment of comments) {
            if (comment.type === 'Block' && comment.value.includes('@package')) {
                hasPackageAnnotation = true;
            }
        }

        if (!hasPackageAnnotation) {
            context.report({
                loc: {
                    start: { line: 1, column: 0 },
                    end: { line: 1, column: 0 },
                },
                message: 'Missing package annotation',
            });
        }

        return {};
    },
};
