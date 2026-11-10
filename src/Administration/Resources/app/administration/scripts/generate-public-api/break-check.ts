/**
 * @sw-package framework
 */
import type { Snapshot } from './snapshot';

/**
 * @private
 *
 * Classifies the difference between the snapshots of the base and the head commit and returns the summary
 * that the break check prints. `headVersion` is the `major.minor` version the base branch releases next.
 */
export type ClassifyChanges = (base: Snapshot, head: Snapshot, options: { headVersion: string }) => string;

/**
 * @private
 *
 * Classifies nothing yet. The unit kinds arrive one at a time, and `expected-failures.ts` lists what is missing.
 */
export const classifyChanges: ClassifyChanges = () => '';
