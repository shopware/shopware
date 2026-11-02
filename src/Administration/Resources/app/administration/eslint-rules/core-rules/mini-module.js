/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');
const { parse } = require('@typescript-eslint/parser');

/**
 * The spec files in a mini-module's `tests/` that test what other code sees, so they import only `../index`.
 *
 * @example
 * CONTRACT_SPEC.test('src/core/order/primary-delivery/tests/pinned.spec.ts') // → true
 * CONTRACT_SPEC.test('src/core/order/primary-delivery/tests/pinned.spec/search.spec.ts') // → true, a split spec
 */
const CONTRACT_SPEC = /(^|\/)(requirements|pinned|regressions|public-api)\.spec(\/[^/]+\.spec)?\.[jt]s$/;

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
 * Finds the mini-module a path belongs to and the part of it the path points at.
 * A mini-module is a directory below the Administration `src/` with an `index.ts`, its own `src/` and `tests/`,
 * and optionally a `private.ts`, `register.ts` and `dependencies.ts`.
 *
 * @example
 * findMiniModule('src/core/order/primary-delivery/src/primary-delivery.ts', cwd)
 * // → { root: 'src/core/order/primary-delivery', part: 'src', rest: 'primary-delivery.ts' }
 * findMiniModule('src/core/order/primary-delivery/private', cwd)
 * // → { root: 'src/core/order/primary-delivery', part: 'private', rest: '' }
 *
 * @param {string} relativePath - posix path relative to the Administration root
 * @param {string} cwd - the Administration root, used to look for the `tests/` folder next to a file at the module's root
 * @returns {{ root: string, part: 'src'|'tests'|'index'|'private'|'register'|'dependencies', rest: string } | null}
 */
function findMiniModule(relativePath, cwd) {
    const inside = /^(src\/.+?)\/(src|tests)\/(.+)$/.exec(relativePath);
    if (inside) {
        return { root: inside[1], part: inside[2], rest: inside[3] };
    }

    const entry = /^(src\/.+)\/(index|private|register|dependencies)(\.[jt]s)?$/.exec(relativePath);
    if (entry && fs.existsSync(path.join(cwd, entry[1], 'tests'))) {
        return { root: entry[1], part: entry[2], rest: '' };
    }

    return null;
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
 * Reads the instance members a mini-module's `index.ts` lists in `definePublicComponent({ public: { … } })`:
 * the props, computed values, methods and native-setup bindings that other code and extensions may use on the component.
 * Use it to check code against the public API without loading the component.
 *
 * @example
 * // index.ts: definePublicComponent({ name: 'sw-x', load, public: { props: ['value'], blocks: ['sw_x'] } })
 * readPublicMembers('src/module/sw-x', cwd) // → { props: ['value'], computed: [], methods: [], setup: [] }
 *
 * @param {string} root - the module's root, relative to `cwd`
 * @param {string} cwd - the Administration root
 * @returns {{ props: string[], computed: string[], methods: string[], setup: string[] }} empty lists when index.ts declares none
 */
function readPublicMembers(root, cwd) {
    const members = Object.fromEntries(INSTANCE_MEMBER_KINDS.map((kind) => [kind, []]));
    const index = path.join(cwd, root, 'index.ts');
    if (!fs.existsSync(index)) {
        return members;
    }

    const declaration = findNode(
        parse(fs.readFileSync(index, 'utf-8')),
        (node) => node.type === 'CallExpression' && node.callee.name === 'definePublicComponent',
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

module.exports = { CONTRACT_SPEC, findMiniModule, readPublicMembers };
