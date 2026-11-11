/**
 * @sw-package framework
 */
import type { Finding } from './findings';
import type { Snapshot } from './snapshot';

/**
 * @private
 *
 * Classifies the difference between the snapshots of the base and the head commit.
 * `headVersion` is the `major.minor` version the base branch releases next.
 */
export type ClassifyChanges = (base: Snapshot, head: Snapshot, options: { headVersion: string }) => Finding[];

/**
 * @private
 *
 * Classifies nothing yet. The unit kinds arrive one at a time, and `expected-failures.ts` lists what is missing.
 */
export const classifyChanges: ClassifyChanges = () => [];
