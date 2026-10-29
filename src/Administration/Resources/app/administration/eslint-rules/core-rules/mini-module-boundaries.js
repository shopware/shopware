/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

/**
 * Finds the mini-module a path belongs to and the part of it the path points at.
 * A mini-module is a directory below the Administration `src/` with an `index.ts`, its own `src/` and `tests/`,
 * and optionally a `private.ts`.
 *
 * @example
 * findMiniModule('src/core/order/primary-delivery/src/primary-delivery.ts', cwd)
 * // → { root: 'src/core/order/primary-delivery', part: 'src', rest: 'primary-delivery.ts' }
 * findMiniModule('src/core/order/primary-delivery/private', cwd)
 * // → { root: 'src/core/order/primary-delivery', part: 'private', rest: '' }
 *
 * @param {string} relativePath - posix path relative to the Administration root
 * @param {string} cwd - the Administration root, used to look for the `tests/` folder next to an index or private.ts
 * @returns {{ root: string, part: 'src'|'tests'|'private'|'index', rest: string } | null}
 */
function findMiniModule(relativePath, cwd) {
    const inside = /^(src\/.+?)\/(src|tests)\/(.+)$/.exec(relativePath);
    if (inside) {
        return { root: inside[1], part: inside[2], rest: inside[3] };
    }

    const entry = /^(src\/.+)\/(index|private)(\.[jt]s)?$/.exec(relativePath);
    if (entry && fs.existsSync(path.join(cwd, entry[1], 'tests'))) {
        return { root: entry[1], part: entry[2], rest: '' };
    }

    return null;
}

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
            if (!targetModule || !['src', 'private'].includes(targetModule.part)) {
                return;
            }

            if (importerModule?.root !== targetModule.root) {
                context.report({
                    node: node.source,
                    messageId: targetModule.part === 'src' ? 'srcImport' : 'privateImport',
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
