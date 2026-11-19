/**
 * @sw-package framework
 */

/**
 * @private
 *
 * Sorts by code point. `localeCompare` depends on the ICU data of the Node.js build, so its order can differ
 * between two machines.
 */
export function byCodePoint(a: string, b: string): number {
    if (a === b) {
        return 0;
    }

    return a < b ? -1 : 1;
}
