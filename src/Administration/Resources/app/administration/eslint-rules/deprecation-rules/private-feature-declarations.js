/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');
const { parse } = require('@typescript-eslint/parser');

const isMiniModuleRoot = (directory) =>
    ['index.ts', 'private.ts'].some((entry) => fs.existsSync(path.join(directory, entry))) &&
    ['src', 'tests'].every((entry) => fs.existsSync(path.join(directory, entry)));

/**
 * Tells which part of a mini-module a file is, for the markers its exports need.
 * A mini-module is a directory with an `index.ts`, or only a `private.ts` when it is nested, a `src/` and a `tests/` folder.
 * Its `index.ts` is public API, also for extensions, and every export there is marked `@public`.
 * Its `private.ts` is for Administration code only, and every export there is marked `@private`.
 * Its `register.ts` registers what `index.ts` declares, so it needs no marker either.
 * Its `src/` and `dependencies.ts` mark every export too, so a reader of the source sees what is exposed:
 * `@public` when `index.ts` exposes it, `@private` otherwise.
 *
 * @example
 * getMiniModulePart('src/module/sw-order/page/sw-order-list/index.ts') // → { part: 'index', root: 'src/module/sw-order/page/sw-order-list' }
 * getMiniModulePart('src/app/main.ts') // → null
 *
 * @param {string} filename
 * @returns {{ part: 'index'|'private'|'register'|'src', root: string }|null}
 */
const getMiniModulePart = (filename) => {
    const entry = /^(index|private|register|dependencies)\.[jt]s$/.exec(path.basename(filename));
    if (entry && isMiniModuleRoot(path.dirname(filename))) {
        return { part: entry[1] === 'dependencies' ? 'src' : entry[1], root: path.dirname(filename) };
    }

    const segments = filename.split(path.sep);
    const sourceIndex = segments.lastIndexOf('src');
    const root = segments.slice(0, sourceIndex).join(path.sep);

    return sourceIndex > 0 && isMiniModuleRoot(root) ? { part: 'src', root } : null;
};

const withoutExtension = (file) => file.replace(/\.[jt]s$/, '');

/** The source of a mini-module's `index.ts`, or nothing for a nested private module, which exposes nothing. */
const readIndex = (root) => {
    const index = path.join(root, 'index.ts');

    return fs.existsSync(index) ? fs.readFileSync(index, 'utf-8') : '';
};

/**
 * Returns the names each file of a mini-module's `src/` exposes through its `index.ts`, keyed by the file without
 * its extension: re-exported names, `*` for `export *`, and `default` for a component that `load: () => import()` loads.
 *
 * @example
 * // index.ts: export { getPrimaryDelivery } from './src/primary-delivery';
 * getExposedNames(root).get(`${root}/src/primary-delivery`); // → Set { 'getPrimaryDelivery' }
 *
 * @param {string} root
 * @param {string} [index] - the source of `index.ts`, read from disk when not given; a nested module has none
 * @returns {Map<string, Set<string>>}
 */
const getExposedNames = (root, index = readIndex(root)) => {
    const exposed = new Map();
    const expose = (specifier, names) => {
        const file = withoutExtension(path.resolve(root, specifier));
        exposed.set(file, new Set([...(exposed.get(file) ?? []), ...names]));
    };

    for (const [, list, specifier] of index.matchAll(/export\s+(?:type\s+)?\{([^}]*)\}\s*from\s*['"](\.[^'"]+)['"]/g)) {
        expose(
            specifier,
            list.split(',').map((name) => name.trim().replace(/^type\s+/, '').split(/\s+as\s+/)[0]).filter(Boolean),
        );
    }
    for (const [, specifier] of index.matchAll(/export\s+\*\s+from\s*['"](\.[^'"]+)['"]/g)) {
        expose(specifier, ['*']);
    }
    for (const [, specifier] of index.matchAll(/import\(\s*['"](\.[^'"]+)['"]\s*\)/g)) {
        expose(specifier, ['default']);
    }

    return exposed;
};

/**
 * Tells whether the marker of a `src/` export contradicts what `index.ts` exposes, and returns the fitting message.
 * A missing marker counts as a contradiction too, because every export in `src/` needs one.
 *
 * @example
 * getMarkerProblem('', true) // → 'index.ts exposes this export, so mark it @public.'
 *
 * @param {string} comment - the leading block comment of the export
 * @param {boolean} isExposed
 * @returns {string|null}
 */
const getMarkerProblem = (comment, isExposed) => {
    const isPublic = comment.includes('@public');
    const isPrivate = comment.includes('@private');

    if (isExposed && (!isPublic || isPrivate)) {
        return 'index.ts exposes this export, so mark it @public.';
    }

    if (!isExposed && (!isPrivate || isPublic)) {
        return 'index.ts does not expose this export, so mark it @private.';
    }

    return null;
};

const getExportedNames = (node) => {
    if (node.type === 'ExportDefaultDeclaration') {
        return ['default'];
    }

    if (node.declaration?.declarations) {
        return node.declaration.declarations.flatMap((declarator) => (declarator.id.type === 'Identifier' ? [declarator.id.name] : []));
    }

    if (node.declaration?.id) {
        return [node.declaration.id.name];
    }

    return node.specifiers.map((specifier) => specifier.exported.name);
};

const getLeadingBlockComment = (node, comments = findComments(node)) => {
    const leadingComment = comments?.find(c => c.loc.end.line === (node.loc.start.line - 1));

    return leadingComment?.type === 'Block' ? leadingComment.value : null;
};

const nodeContainsLeadingBlockComment = (node) => {
    const comment = getLeadingBlockComment(node);

    return comment !== null && (comment.includes('@private') || comment.includes('@deprecated tag:'));
};

function findComments(node) {
    while (node.comments === undefined && node.parent) {
        node = node.parent;
    }

    return node.comments;
}

const listSourceFiles = (directory) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const file = path.join(directory, entry.name);

    if (entry.isDirectory()) {
        return listSourceFiles(file);
    }

    return /(?<!\.d)\.[jt]s$/.test(entry.name) && !/\.spec\.[jt]s$/.test(entry.name) ? [file] : [];
});

/**
 * Checks the markers of every export in a mini-module's `src/` and `dependencies.ts` against an `index.ts`.
 * The rule runs this when it lints `index.ts`, because ESLint caches results per file:
 * a change to `index.ts` alone would otherwise leave the old results of the `src/` files standing.
 *
 * @example
 * // index.ts no longer exports `helper`, src/helper.ts still marks it @public
 * findStaleMarkers(root, indexSource) // → [{ file: 'src/helper.ts', line: 2, name: 'helper', message: 'index.ts does not expose …' }]
 *
 * @param {string} root
 * @param {string} index - the source of `index.ts`
 * @returns {{ file: string, line: number, name: string, message: string }[]}
 */
const findStaleMarkers = (root, index) => {
    const exposedByFile = getExposedNames(root, index);
    const dependencies = path.join(root, 'dependencies.ts');
    const files = [
        ...(fs.existsSync(path.join(root, 'src')) ? listSourceFiles(path.join(root, 'src')) : []),
        ...(fs.existsSync(dependencies) ? [dependencies] : []),
    ];

    return files.flatMap((file) => {
        const program = parse(fs.readFileSync(file, 'utf-8'), { comment: true, loc: true, jsx: false });
        const exposedNames = exposedByFile.get(withoutExtension(file)) ?? new Set();

        return program.body
            .filter((node) => ['ExportNamedDeclaration', 'ExportDefaultDeclaration'].includes(node.type))
            .flatMap((node) => {
                const comment = getLeadingBlockComment(node, program.comments) ?? '';
                if (!comment.includes('@public') && !comment.includes('@private')) {
                    return [];
                }

                return getExportedNames(node).flatMap((name) => {
                    const message = getMarkerProblem(comment, exposedNames.has('*') || exposedNames.has(name));

                    return message ? [{ file: path.relative(root, file), line: node.loc.start.line, name, message }] : [];
                });
            });
    });
};

/**
 * This rule validates that new features are either private or deprecated to be private in the future.
 * The index.ts of a mini-module is the exception: its exports are marked @public.
 *
 * Invalid:
 * class Example {}
 * Component.register('foo', {});
 * Shopware.Component.register('bar', {});
 * Module.register('foo', {});
 * Shopware.Module.register('bar', {});
 * export const foo = 'foo';
 * export default foo;
 *
 * Valid:
 * \**
 *  * @private
 *  *\
 * class Example {}
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * class Example {}
 *
 * \**
 *  * @private
 *  *\
 * Component.register('foo', {});
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * Shopware.Component.register('bar', {});
 *
 * \**
 *  * @private
 *  *\
 * Module.register('foo', {});
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * Shopware.Module.register('bar', {});
 *
 * \**
 *  * @private
 *  *\
 * export const foo = 'foo';
 *
 * \**
 *  * @deprecated tag:v6.X.0 - Will be @private
 *  *\
 * export default foo;
 */
/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',

        docs: {
            description: 'New features have to be private',
            recommended: true,
            url: 'https://handbook.shopware.com/Product/Product/Components/Admin/NewFeatures',
        },
    },
    create(context) {
        const filename = path.resolve(context.filename ?? context.getFilename());
        const { part, root } = getMiniModulePart(filename) ?? {};

        if (part === 'register') {
            return {};
        }

        const exposedNames = part === 'src' ? getExposedNames(root).get(withoutExtension(filename)) ?? new Set() : null;

        const checkExport = (node) => {
            const comment = getLeadingBlockComment(node) ?? '';
            const isPublic = comment.includes('@public');
            const isPrivate = comment.includes('@private');

            if (part === 'src') {
                const isExposed = exposedNames.has('*') || getExportedNames(node).some((name) => exposedNames.has(name));
                const message = getMarkerProblem(comment, isExposed);

                if (message) {
                    context.report({ node, message });
                }

                return;
            }

            if (part === 'index') {
                if (!isPublic || isPrivate) {
                    context.report({ node, message: 'The index.ts of a mini-module is public API. Mark this export @public, or move it to private.ts.' });
                }

                return;
            }

            if (part === 'private') {
                if (!isPrivate || isPublic) {
                    context.report({ node, message: 'The private.ts of a mini-module never reaches extensions. Mark this export @private, or move it to index.ts.' });
                }

                return;
            }

            if (isPublic) {
                context.report({ node, message: 'Only the index.ts of a mini-module may export @public API.' });

                return;
            }

            if (!nodeContainsLeadingBlockComment(node)) {
                context.report({
                    node,
                    message: 'New exports need to be private. Old exports should be @deprecated tag:v6.X.0 - Will be private',
                });
            }
        };

        return {
            Program(node) {
                if (part !== 'index') {
                    return;
                }

                for (const stale of findStaleMarkers(root, context.sourceCode.text)) {
                    context.report({ node, loc: { line: 1, column: 0 }, message: `${stale.file}:${stale.line} \`${stale.name}\`: ${stale.message}` });
                }
            },
            ExportDefaultDeclaration: checkExport,
            ExportNamedDeclaration: checkExport,
            ExportAllDeclaration(node) {
                if (part === 'index' || part === 'private') {
                    checkExport(node);
                }
            },
            ExpressionStatement(node) {
                const root = node.expression?.callee?.object;
                const rootObject = root?.name;
                const property = node.expression?.callee?.property?.name;

                if (!property || property !== 'register') {
                    return;
                }

                let isFeatureRegister = false;

                if (rootObject === 'Component' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && root?.object && root?.property && root?.object?.name === 'Shopware' && root.property.name === 'Component' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && rootObject === 'Module' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && root?.object && root?.property && root?.object?.name === 'Shopware' && root.property.name === 'Module' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && rootObject === 'Service' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister && root?.callee?.object && root?.callee?.property && root?.callee?.object.name === 'Shopware' && root.callee.property.name === 'Service' && property === 'register') {
                    isFeatureRegister = true;
                }

                if (!isFeatureRegister || nodeContainsLeadingBlockComment(node)) {
                    return;
                }

                context.report({
                    node,
                    message: 'New features need to be private. Old features should be @deprecated tag:v6.X.0 - Will be private',
                });
            },
        };
    },
};
