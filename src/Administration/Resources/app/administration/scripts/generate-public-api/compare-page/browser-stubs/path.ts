/**
 * @sw-package framework
 *
 * Stands in for `node:path` in the bundle of the compare page. `scripts/feature-flags.ts` builds the path of
 * `feature.yaml` when it is imported, so joining has to work. The page never reads that path.
 */

/** @private */
export const sep = '/';

/** @private */
export function join(...parts: string[]): string {
    return parts.join(sep);
}

/** @private */
export const resolve = join;

/** @private */
export default { sep, join, resolve };
