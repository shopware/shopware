/**
 * @sw-package framework
 */

const path = require('path');
const { findMiniModule } = require('./mini-module');

/** The `shopware:*` modules, which publish the global `Shopware` object as imports. */
const SHOPWARE_MODULE = /^shopware:/;

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: "Inside the Administration, only the dependencies.ts of a mini-module imports from 'shopware:*'",
        },
        messages: {
            outsideDependencies:
                "Only the dependencies.ts of a mini-module imports '{{source}}'. Import it there, or import the Administration source directly.",
        },
        schema: [],
    },
    create(context) {
        const cwd = context.cwd ?? process.cwd();
        const filename = path
            .relative(cwd, context.filename ?? context.getFilename())
            .split(path.sep)
            .join('/');

        if (findMiniModule(filename, cwd)?.part === 'dependencies') {
            return {};
        }

        function check(node) {
            const source = node.source?.value;

            if (typeof source === 'string' && SHOPWARE_MODULE.test(source)) {
                context.report({ node: node.source, messageId: 'outsideDependencies', data: { source } });
            }
        }

        return {
            ImportDeclaration: check,
            ImportExpression: check,
            ExportNamedDeclaration: check,
            ExportAllDeclaration: check,
        };
    },
};
