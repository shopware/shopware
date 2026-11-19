/**
 * @sw-package framework
 */
import { buildModel } from './model';
import type { SnapshotOptions } from './model';
import { printIndex } from './print-index';
import { printReport } from './print-report';
import { readSourceTree } from './source-tree';
import type { SourceTree } from './source-tree';

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
 */
export type GenerateSnapshot = (administrationRoot: string, options: SnapshotOptions) => Snapshot;

/**
 * @private
 *
 * The snapshot of a source tree that is already read. It holds the unit kinds built so far: the registered
 * components with the blocks of their templates. `expected-failures.ts` lists what is missing.
 */
export function snapshotOf(tree: SourceTree, options: SnapshotOptions): Snapshot {
    const model = buildModel(tree, options);
    const files: Record<string, string> = { 'index.json': printIndex(model) };

    model.components.forEach((unit) => {
        files[`${unit.file}.api.md`] = printReport(unit, model.flags);
    });

    return { files };
}

/** @private */
export const generateSnapshot: GenerateSnapshot = (administrationRoot, options) =>
    snapshotOf(readSourceTree(administrationRoot), options);
