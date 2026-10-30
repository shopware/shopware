/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

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

module.exports = { findMiniModule };
