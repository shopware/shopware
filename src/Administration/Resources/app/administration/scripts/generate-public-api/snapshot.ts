/**
 * @sw-package framework
 */
import { buildModel } from './model';
import type { Model, SnapshotOptions } from './model';
import { printIndex } from './print-index';
import { printReport } from './print-report';
import { readSourceTree } from './source-tree';
import type { SourceTree } from './source-tree';

/**
 * @private
 *
 * The files of a snapshot, by path below `public-api/`.
 */
export interface SnapshotFiles {
    files: Record<string, string>;
}

/**
 * @private
 *
 * The public API of one source tree. `files` holds what the generator writes, and `model` the data behind it,
 * which the break check compares. The model knows the lines of the sources, the files do not.
 */
export interface Snapshot extends SnapshotFiles {
    model: Model;
}

/**
 * The snapshot lists blocks per component, so the blocks of a template that no component uses would be missing
 * from it without anyone noticing.
 */
function assertEveryTemplateIsUsed({ unusedBlocks }: Model): void {
    if (unusedBlocks.length > 0) {
        throw new Error(
            'These blocks belong to no registered component, so the snapshot would not list them:\n' +
                unusedBlocks.map(({ block, file }) => `${block} in ${file}`).join('\n'),
        );
    }
}

/**
 * @private
 *
 * The snapshot of a source tree that is already read. It holds the unit kinds built so far: the registered
 * components with the blocks of their templates. `expected-failures.ts` lists what is missing.
 */
export function snapshotOf(tree: SourceTree, options: SnapshotOptions): Snapshot {
    const model = buildModel(tree, options);

    assertEveryTemplateIsUsed(model);
    const files: Record<string, string> = { 'index.json': printIndex(model) };

    model.components.forEach((unit) => {
        files[`${unit.file}.api.md`] = printReport(unit, model.flags);
    });

    return { files, model };
}

/**
 * @private
 *
 * Generates the snapshot of the Administration source tree below `administrationRoot`.
 */
export function generateSnapshot(administrationRoot: string, options: SnapshotOptions): Snapshot {
    return snapshotOf(readSourceTree(administrationRoot), options);
}
