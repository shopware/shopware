/**
 * @sw-package framework
 */

import { generated, type FlatSourceChunk, type SourceChunk } from './chunks';
import { render, toFlatChunks } from './render-chunks';

type SourceEdit = {
    start: number;
    end: number;
    replacement: string | SourceChunk[];
};

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
 * Sourcemap support is intentionally added by the dedicated sourcemap PR. Until
 * then the transform has the same behavior as a normal string replacement step.
 */
function applySourceEdits(source: string, _filename: string, edits: SourceEdit[]): AppliedSourceEdits {
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
            chunks.push(...toFlatChunks(normalizeReplacement(edit.replacement), source));

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

module.exports = {
    applySourceEdits,
};

export { type AppliedSourceEdits, type SourceEdit, applySourceEdits };
