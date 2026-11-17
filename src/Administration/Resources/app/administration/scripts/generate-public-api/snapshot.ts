/**
 * @sw-package framework
 */

/**
 * @private
 *
 * The public API of one source tree. `files` holds what the generator writes, by path below `public-api/`.
 */
export interface Snapshot {
    files: Record<string, string>;
}

/**
 * @private
 *
 * Generates the snapshot of the Administration source tree below `administrationRoot`.
 * `majorFlags` are the feature flags that are on in the major state, for example `v6.8.0.0`.
 */
export type GenerateSnapshot = (administrationRoot: string, options: { majorFlags: readonly string[] }) => Snapshot;

/**
 * @private
 *
 * Generates nothing yet. The unit kinds arrive one at a time, and `expected-failures.ts` lists what is missing.
 */
export const generateSnapshot: GenerateSnapshot = () => ({ files: {} });
