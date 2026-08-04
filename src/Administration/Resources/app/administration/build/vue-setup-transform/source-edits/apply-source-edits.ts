/**
 * @sw-package framework
 */

/**
 * Applies template and script edits to complete SFC source.
 *
 * Callers can pass plain generated strings or structured source chunks; both are flattened into the
 * same replacement stream that keeps generated and original ranges distinguishable.
 */

import { ShopwareSetupTransformError } from '../utils/transform-error';
import { generated, type SourceChunk } from './chunks';
import { render } from './render-chunks';

/**
 * Describes one replacement in absolute SFC coordinates.
 */
type SourceEdit = {
    start: number;
    end: number;
    replacement: string | SourceChunk[];
};

/**
 * Transformed SFC code. `map` is always `null`: this step rewrites source without producing a sourcemap.
 */
type AppliedSourceEdits = {
    code: string;
    map: null;
};

/**
 * Converts string replacements into generated chunks so callers can keep the API concise.
 */
function normalizeReplacement(replacement: string | SourceChunk[]): SourceChunk[] {
    return typeof replacement === 'string' ? [generated(replacement)] : replacement;
}

/**
 * Applies non-overlapping source edits and returns transformed code.
 *
 * The template and script edits this receives address disjoint SFC blocks, so an overlap is an
 * analyzer bug and is rejected rather than silently dropped. Zero-width insertions that share a
 * position with the following edit are fine, which is what the generated registration template for a
 * template-less override relies on.
 *
 * This step does not generate a sourcemap; it behaves like a plain string replacement and returns
 * `map: null`.
 */
function applySourceEdits(source: string, edits: SourceEdit[]): AppliedSourceEdits {
    let cursor = 0;
    const chunks: SourceChunk[] = [];

    [...edits]
        .sort((a, b) => a.start - b.start)
        .forEach((edit) => {
            if (edit.start < cursor) {
                throw new ShopwareSetupTransformError(
                    `Overlapping Shopware setup source edits: an edit at ${edit.start}-${edit.end} starts before the ` +
                        `previous edit ended at ${cursor}. This is an analyzer bug, not an authoring error.`,
                    edit.start,
                );
            }

            chunks.push({
                type: 'original',
                start: cursor,
                end: edit.start,
            });
            chunks.push(...normalizeReplacement(edit.replacement));

            cursor = edit.end;
        });

    chunks.push({
        type: 'original',
        start: cursor,
        end: source.length,
    });

    return {
        code: render(chunks, source),
        map: null,
    };
}

/**
 * @private
 */
export { type AppliedSourceEdits, type SourceEdit, applySourceEdits };
