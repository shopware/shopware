/**
 * @sw-package framework
 */

const path = require('path');

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Disallow JavaScript files, new code is written in TypeScript',
        },
        schema: [],
        messages: {
            javascriptFile: 'New code is TypeScript. Rename this file to {{name}}.ts.',
        },
    },

    create(context) {
        const filename = context.filename;

        if (path.extname(filename) !== '.js') {
            return {};
        }

        return {
            Program() {
                context.report({
                    loc: { line: 1, column: 0 },
                    messageId: 'javascriptFile',
                    data: { name: path.basename(filename, '.js') },
                });
            },
        };
    },
};
