/**
 * @sw-package framework
 */

/**
 * Applies template and script edits to complete SFC source.
 *
 * Callers can pass plain generated strings or structured source chunks; both are flattened into the
 * same replacement stream that keeps generated and original ranges distinguishable.
 */

import { generated, type FlatSourceChunk, type SourceChunk } from './chunks';
import { render, toFlatChunks } from './render-chunks';

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
 * This step does not generate a sourcemap; it behaves like a plain string replacement and returns
 * `map: null`.
 */
function applySourceEdits(
    source: string,
    _filename: string,
    edits: SourceEdit[],
    protectedRanges: readonly (readonly [number, number])[] = [],
): AppliedSourceEdits {
    let cursor = 0;
    const chunks: FlatSourceChunk[] = [];

    [...edits]
        .sort((a, b) => a.start - b.start)
        .forEach((edit) => {
            if (edit.start < cursor) {
                return;
            }

            chunks.push({
                type: 'original',
                start: cursor,
                end: edit.start,
            });
            chunks.push(...toFlatChunks(normalizeReplacement(edit.replacement), source, 0, protectedRanges));

            cursor = edit.end;
        });

    chunks.push({
        type: 'original',
        start: cursor,
        end: source.length,
    });

    return {
        code: render(chunks, source, 0, protectedRanges),
        map: null,
    };
}

export { type AppliedSourceEdits, type SourceEdit, applySourceEdits };
