/**
 * @package admin
 */

/* eslint-disable max-len */
module.exports = {
    meta: {
        type: 'snippets',

        docs: {
            description: 'If global.default translations are used for the same translation, they have to be used',
            recommended: true,
        },

        fixable: "code",
    },
    create(context) {
        const sourceCode = context.getSourceCode();
        const comments = sourceCode.getAllComments();

        // Check if the file is a js, ts, spec.js or spec.ts file
        const isSnippetFile = /^[a-z]{2}-[A-Z]{2}\.json$/.test(context.getFilename());

        // console.log('file:', context.getFilename());

        // Skip if it's a spec file or not a js/ts file
        if (!isSnippetFile) {
            return {};
        }

        // ToDo: Remove after Debug
        const x = context.getFilename();
        console.log('Match:', context.getFilename());

        return {};
    },
};
