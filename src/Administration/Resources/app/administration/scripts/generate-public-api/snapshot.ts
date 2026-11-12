/**
 * @sw-package framework
 */
import { byCodePoint } from './code-point-order';
import { buildModel } from './model';
import type { ComponentUnit, Model, SnapshotOptions } from './model';
import { printContracts } from './print-contract';
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

function printIndex({ components }: Model): string {
    const entries = components.map(({ name, file, visibility, extends: parent, blocks }): [string, object] => [
        name,
        {
            file,
            visibility,
            extends: parent,
            blocks: [...new Set(blocks.map((block) => block.name))].sort(byCodePoint),
        },
    ]);

    return `${JSON.stringify({ components: Object.fromEntries(entries) }, null, 4)}\n`;
}

function printComponent(unit: ComponentUnit, { flags }: Model): Record<string, string> {
    const { files, hasMajorCopy } = printContracts(unit, flags);

    return { ...files, [`${unit.file}.api.md`]: printReport(unit, flags, hasMajorCopy) };
}

/**
 * @private
 *
 * The snapshot of a source tree that is already read. It holds the unit kinds built so far: the registered
 * components with the blocks of their templates. `expected-failures.ts` lists what is missing.
 */
export function snapshotOf(tree: SourceTree, options: SnapshotOptions): Snapshot {
    const model = buildModel(tree, options);
    const files: Record<string, string> = { 'index.json': printIndex(model) };

    model.components.forEach((unit) => Object.assign(files, printComponent(unit, model)));

    return { files };
}

/** @private */
export const generateSnapshot: GenerateSnapshot = (administrationRoot, options) =>
    snapshotOf(readSourceTree(administrationRoot), options);
