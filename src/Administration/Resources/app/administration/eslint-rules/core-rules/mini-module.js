/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');
const { parse } = require('@typescript-eslint/parser');

/**
 * The spec files in a mini-module's `tests/` that test what other code sees, so they import only the module's
 * entry file: `../index`, or `../private` for a module without an `index.ts`, like a nested private module.
 *
 * @example
 * CONTRACT_SPEC.test('src/core/order/primary-delivery/tests/pinned.spec.ts') // → true
 * CONTRACT_SPEC.test('src/core/order/primary-delivery/tests/pinned.spec/search.spec.ts') // → true, a split spec
 */
const CONTRACT_SPEC = /(^|\/)(requirements|pinned|regressions|public-api|private-api)\.spec(\/[^/]+\.spec)?\.[jt]s$/;

/**
 * The kinds of members a `public` list of `definePublicComponent()` names, that a component instance carries.
 * `setup` holds the `swDefinePublic()` bindings of a native-setup component.
 */
const INSTANCE_MEMBER_KINDS = [
    'props',
    'computed',
    'methods',
    'setup',
];

/**
 * Whether a directory is a mini-module: below the Administration `src/`, with its own `src/` and `tests/`,
 * and an `index.ts`, or only a `private.ts` for a nested private module.
 *
 * @param {string} directory - posix path relative to `cwd`
 * @param {string} cwd - the Administration root
 * @returns {boolean}
 */
function isMiniModuleRoot(directory, cwd) {
    return (
        directory.startsWith('src/') &&
        ['index.ts', 'private.ts'].some((entry) => fs.existsSync(path.join(cwd, directory, entry))) &&
        ['src', 'tests'].every((folder) => fs.existsSync(path.join(cwd, directory, folder)))
    );
}

/**
 * Finds the innermost mini-module a path belongs to and the part of it the path points at.
 * A mini-module is a directory below the Administration `src/` with an `index.ts` or only a `private.ts`,
 * its own `src/` and `tests/`, and optionally a `private.ts`, `register.ts`, `init.ts` and `dependencies.ts`.
 * A nested private module lives in the `src/` of the module that mounts its component.
 *
 * @example
 * findMiniModule('src/core/order/primary-delivery/src/primary-delivery.ts', cwd)
 * // → { root: 'src/core/order/primary-delivery', part: 'src', rest: 'primary-delivery.ts' }
 * findMiniModule('src/core/order/primary-delivery/private', cwd)
 * // → { root: 'src/core/order/primary-delivery', part: 'private', rest: '' }
 *
 * @param {string} relativePath - posix path relative to the Administration root
 * @param {string} cwd - the Administration root, used to look for the `tests/` folder next to a file at the module's root
 * @returns {{ root: string, part: 'src'|'tests'|'index'|'private'|'register'|'init'|'dependencies'|'global', rest: string } | null}
 */
function findMiniModule(relativePath, cwd) {
    const entry = /^(src\/.+)\/(index|private|register|init|dependencies|global)(\.d)?(\.[jt]s)?$/.exec(relativePath);
    if (entry && isMiniModuleRoot(entry[1], cwd)) {
        return { root: entry[1], part: entry[2], rest: '' };
    }

    const segments = relativePath.split('/');
    for (let at = segments.length - 2; at > 0; at -= 1) {
        const root = segments.slice(0, at).join('/');
        if (['src', 'tests'].includes(segments[at]) && isMiniModuleRoot(root, cwd)) {
            return { root, part: segments[at], rest: segments.slice(at + 1).join('/') };
        }
    }

    return null;
}

/**
 * The module whose `src/` holds a nested private module, which is the only code that may import its `private.ts`.
 *
 * @example
 * findEnclosingModule('src/module/sw-x/page/sw-x-index/src/sw-x-card', cwd) // → 'src/module/sw-x/page/sw-x-index'
 *
 * @param {string} root - the nested module's root, relative to `cwd`
 * @param {string} cwd - the Administration root
 * @returns {string|null}
 */
function findEnclosingModule(root, cwd) {
    const enclosing = findMiniModule(root, cwd);

    return enclosing?.part === 'src' ? enclosing.root : null;
}

/**
 * Depth-first search for the first node in an AST that matches.
 *
 * @param {object} node
 * @param {(node: object) => boolean} matches
 * @returns {object|null}
 */
function findNode(node, matches) {
    if (!node || typeof node.type !== 'string') {
        return null;
    }
    if (matches(node)) {
        return node;
    }

    for (const [key, value] of Object.entries(node)) {
        if (key === 'parent') {
            continue;
        }

        for (const child of Array.isArray(value) ? value : [value]) {
            const found = child && typeof child === 'object' ? findNode(child, matches) : null;
            if (found) {
                return found;
            }
        }
    }

    return null;
}

/**
 * Reads the instance members a mini-module's `index.ts` lists in `definePublicComponent({ public: { … } })`,
 * or its `private.ts` in `definePrivateComponent({ public: { … } })`:
 * the props, computed values, methods and native-setup bindings that other code and extensions may use on the component.
 * Use it to check code against the public API without loading the component.
 *
 * @example
 * // index.ts: definePublicComponent({ name: 'sw-x', load, public: { props: ['value'], blocks: ['sw_x'] } })
 * readPublicMembers('src/module/sw-x', cwd) // → { props: ['value'], computed: [], methods: [], setup: [] }
 *
 * @param {string} root - the module's root, relative to `cwd`
 * @param {string} cwd - the Administration root
 * @param {'index.ts'|'private.ts'} [entry] - the file that declares the component
 * @returns {{ props: string[], computed: string[], methods: string[], setup: string[] }} empty lists when the file declares none
 */
function readPublicMembers(root, cwd, entry = 'index.ts') {
    const members = Object.fromEntries(INSTANCE_MEMBER_KINDS.map((kind) => [kind, []]));
    const index = path.join(cwd, root, entry);
    if (!fs.existsSync(index)) {
        return members;
    }

    const declaration = findNode(
        parse(fs.readFileSync(index, 'utf-8')),
        (node) =>
            node.type === 'CallExpression' && ['definePublicComponent', 'definePrivateComponent'].includes(node.callee.name),
    );
    const publicApi = declaration?.arguments[0]?.properties?.find((property) => property.key?.name === 'public');

    for (const property of publicApi?.value.properties ?? []) {
        if (INSTANCE_MEMBER_KINDS.includes(property.key?.name) && property.value.type === 'ArrayExpression') {
            members[property.key.name] = property.value.elements
                .filter((element) => element?.type === 'Literal' && typeof element.value === 'string')
                .map((element) => element.value);
        }
    }

    return members;
}

module.exports = { CONTRACT_SPEC, findEnclosingModule, findMiniModule, readPublicMembers };
