/**
 * @sw-package framework
 *
 * Stands in for `node:fs` in the bundle of the compare page. The generator modules that the page uses also hold
 * the functions that read a checkout, and the page never calls those.
 */
function unavailable(): never {
    throw new Error('The compare page has no file system.');
}

/** @private */
export const existsSync = unavailable;

/** @private */
export const readFileSync = unavailable;

/** @private */
export const readdirSync = unavailable;

/** @private */
export default { existsSync, readFileSync, readdirSync };
