/**
 * @sw-package framework
 */

const path = require('path');
const { findMiniModule } = require('./mini-module');

/** Spec files that test the public API, so they import the module the way other code does. */
const CONTRACT_TESTS = /^(requirements|pinned|regressions)\.spec\.[jt]s$/;

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Other code imports a mini-module through its index, and only on purpose through its private.ts',
        },
        messages: {
            srcImport: "'{{source}}' reaches into the src/ of the mini-module '{{root}}'. Import '{{root}}' instead.",
            privateImport:
                "'{{source}}' uses the private API of '{{root}}'. Import '{{root}}' instead, or disable this line and say why.",
            contractTestImport: "Requirements, pinned and regressions specs import their module only through '../index'.",
        },
        schema: [],
    },
    create(context) {
        const cwd = context.cwd ?? process.cwd();
        const toRelative = (absolutePath) => path.relative(cwd, absolutePath).split(path.sep).join('/');
        const importer = toRelative(context.filename ?? context.getFilename());
        const importerModule = findMiniModule(importer, cwd);

        function check(node) {
            const source = node.source?.value;

            if (typeof source !== 'string') {
                return;
            }

            let target;
            if (source.startsWith('.')) {
                target = toRelative(path.resolve(path.dirname(path.resolve(cwd, importer)), source));
            } else if (source.startsWith('src/')) {
                target = source;
            } else {
                return;
            }

            const targetModule = findMiniModule(target, cwd);
            if (!targetModule || !['src', 'private', 'dependencies'].includes(targetModule.part)) {
                return;
            }

            if (importerModule?.root !== targetModule.root) {
                context.report({
                    node: node.source,
                    messageId: targetModule.part === 'private' ? 'privateImport' : 'srcImport',
                    data: { source, root: targetModule.root },
                });
                return;
            }

            if (importerModule.part === 'tests' && CONTRACT_TESTS.test(importerModule.rest)) {
                context.report({ node: node.source, messageId: 'contractTestImport' });
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
