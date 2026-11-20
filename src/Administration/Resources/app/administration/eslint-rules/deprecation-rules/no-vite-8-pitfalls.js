/**
 * @sw-package framework
 *
 * Upgrade codemod `vite-8`: 6.8 builds Administration code with Vite 8. The rule
 *
 * - marks the top of an extension's own `vite.config.*`, which the upgrade runner lints next to `src`,
 * - marks default imports of packages that ship only CommonJS according to their `package.json` (no
 *   `"type": "module"`, no `module` field, no ESM condition or `.mjs` target in `exports`), because Vite 8 resolves
 *   some of them to a different value.
 *
 * A package is looked up in the `node_modules` folders above the linted file, as Node and Vite resolve it, and then in
 * the `node_modules` of the Administration, which builds the extension. Packages that are not installed are skipped.
 * The texts are in `no-vite-8-pitfalls.data.js`.
 */

const fs = require('fs');
const path = require('path');
const { builtinModules } = require('module');
const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { TODO_PREFIX } = require('./upgrade-helpers/todo-comment');
const data = require('./no-vite-8-pitfalls.data');

const ADMINISTRATION_ROOT = path.resolve(__dirname, '..', '..');
const manifests = new Map();

/**
 * `@scope/name/sub/path` → `{ name: '@scope/name', subpath: 'sub/path' }`.
 */
function splitSpecifier(specifier) {
    const parts = specifier.split('/');
    const length = specifier.startsWith('@') ? 2 : 1;

    if (parts.length < length || parts.slice(0, length).some((part) => part === '')) {
        return { name: null, subpath: '' };
    }

    return { name: parts.slice(0, length).join('/'), subpath: parts.slice(length).join('/') };
}

function readManifest(file) {
    if (!manifests.has(file)) {
        let manifest = null;

        try {
            manifest = JSON.parse(fs.readFileSync(file, 'utf8'));
        } catch {
            // Missing or unreadable: the package is treated as unknown.
        }

        manifests.set(file, manifest);
    }

    return manifests.get(file);
}

/**
 * The `package.json` of `name` as seen from `directory`, falling back to the Administration's `node_modules`.
 */
function findManifest(name, directory) {
    const candidates = [];

    for (let current = path.resolve(directory); ; current = path.dirname(current)) {
        candidates.push(path.join(current, 'node_modules', name, 'package.json'));

        if (path.dirname(current) === current) {
            break;
        }
    }

    candidates.push(path.join(ADMINISTRATION_ROOT, 'node_modules', name, 'package.json'));

    for (const candidate of candidates) {
        const manifest = readManifest(candidate);

        if (manifest) {
            return manifest;
        }
    }

    return null;
}

function hasEsmTarget(exports) {
    if (typeof exports === 'string') {
        return /\.mjs$/.test(exports);
    }

    if (Array.isArray(exports)) {
        return exports.some(hasEsmTarget);
    }

    if (exports && typeof exports === 'object') {
        return Object.keys(exports).some((key) => key === 'import' || key === 'module') || Object.values(exports).some(hasEsmTarget);
    }

    return false;
}

/**
 * Whether a package ships only CommonJS according to its `package.json`.
 */
function isCommonJsOnly(manifest) {
    if (manifest.type === 'module' || manifest.module || manifest['jsnext:main'] || manifest.es2015) {
        return false;
    }

    if (typeof manifest.main === 'string' && /\.mjs$/.test(manifest.main)) {
        return false;
    }

    return !hasEsmTarget(manifest.exports);
}

/**
 * Whether a default import of `specifier` from `directory` reads a CommonJS-only module.
 */
function importsCommonJs(specifier, directory) {
    // A query (`?raw`, `?url`) makes Vite load the file as an asset, not as a module.
    if (specifier.includes('?') || data.ignoredSpecifiers.test(specifier)) {
        return false;
    }

    const { name, subpath } = splitSpecifier(specifier);

    if (!name || builtinModules.includes(name) || name.startsWith('@types/') || /\.m[jt]s$/.test(subpath) || data.assetSubpath.test(subpath)) {
        return false;
    }

    const manifest = findManifest(name, directory);

    return manifest !== null && (/\.cjs$/.test(subpath) || isCommonJsOnly(manifest));
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => {
        const filename = context.physicalFilename ?? context.filename ?? '';

        return {
            Program(program) {
                if (!data.configFile.test(path.basename(filename))) {
                    return;
                }

                // Anchored at the first statement or comment that is not a codemod marker, so a second run finds the marker.
                const first = [...program.body, ...context.sourceCode.getAllComments()]
                    .filter((node) => !String(node.value ?? '').includes(`${TODO_PREFIX}(`))
                    .sort((a, b) => a.range[0] - b.range[0])[0];

                if (first) {
                    api.todo(first, data.configMessage);
                }
            },
            ImportDeclaration(node) {
                const hasDefault = node.specifiers.some((specifier) => specifier.type === 'ImportDefaultSpecifier');

                if (node.importKind === 'type' || !hasDefault || typeof node.source.value !== 'string') {
                    return;
                }

                if (importsCommonJs(node.source.value, path.dirname(filename))) {
                    api.todo(node, data.commonJsMessage);
                }
            },
        };
    },
});
