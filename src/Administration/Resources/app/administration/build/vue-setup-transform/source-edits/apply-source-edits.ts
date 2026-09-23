/**
 * @sw-package framework
 */

/**
 * Applies template and script edits to complete SFC source.
 *
 * Callers can pass plain generated strings or structured source chunks; both become one replacement
 * stream, and the result carries a sourcemap that maps generated code back to the original SFC ranges.
 */

import MagicString, { Bundle, type SourceMap } from 'magic-string';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { generated, type SourceChunk } from './chunks';
import {
    type GeneratedPosition,
    findGeneratedOnlyMappingEdges,
    markGeneratedCodeAsUnmapped,
    moveGeneratedCursor,
} from './sourcemap';

/**
 * Describes one replacement in absolute SFC coordinates.
 */
type SourceEdit = {
    start: number;
    end: number;
    replacement: string | SourceChunk[];
};

/**
 * Transformed SFC code plus the sourcemap that maps the generated code back to the original SFC.
 */
type AppliedSourceEdits = {
    code: string;
    map: SourceMap;
};

/**
 * Mutable accumulator threaded through the chunk-append helpers while one transform runs.
 *
 * Chunks are appended one at a time, so the map cannot be derived from the final string alone: it has
 * to be built up as we go. This holds that in-progress state so each helper can mutate it in place.
 * - `bundle` collects the emitted content (original slices keep their mapping, generated code does not).
 * - `originalSource`/`source` are the same SFC as a MagicString and as a raw string, used to snip
 *   original slices and to advance the cursor by their text.
 * - `generatedCursor` tracks the line/column in the generated output reached so far.
 * - `generatedOnlyPositions` collects the guard edges that {@link markGeneratedCodeAsUnmapped} later
 *   stamps into the map so closest-match lookups can't attribute generated bridge code to user code.
 */
type ApplySourceEditContext = {
    bundle: Bundle;
    originalSource: MagicString;
    source: string;
    generatedCursor: GeneratedPosition;
    generatedOnlyPositions: GeneratedPosition[];
};

/**
 * Adds either generated code or an original source slice to a sourcemap-aware bundle.
 */
function appendChunk(bundle: Bundle, originalSource: MagicString, chunk: SourceChunk): void {
    if (chunk.type === 'generated') {
        if (chunk.code.length > 0) {
            bundle.addSource({
                content: new MagicString(chunk.code),
            });
        }

        return;
    }

    if (chunk.start < chunk.end) {
        bundle.addSource({
            content: originalSource.snip(chunk.start, chunk.end),
        });
    }
}

/**
 * Appends a chunk and keeps track of where generated-only sourcemap edges are needed.
 */
function appendTrackedChunk(context: ApplySourceEditContext, chunk: SourceChunk): void {
    if (chunk.type === 'generated') {
        if (chunk.code.length > 0) {
            context.generatedOnlyPositions.push(...findGeneratedOnlyMappingEdges(context.generatedCursor, chunk.code));
            moveGeneratedCursor(context.generatedCursor, chunk.code);
        }

        appendChunk(context.bundle, context.originalSource, chunk);

        return;
    }

    if (chunk.start < chunk.end) {
        moveGeneratedCursor(context.generatedCursor, context.source.slice(chunk.start, chunk.end));
    }

    appendChunk(context.bundle, context.originalSource, chunk);
}

/**
 * Converts string replacements into generated chunks so callers can keep the API concise.
 */
function normalizeReplacement(replacement: string | SourceChunk[]): SourceChunk[] {
    return typeof replacement === 'string' ? [generated(replacement)] : replacement;
}

/**
 * Applies non-overlapping source edits while keeping original chunks mapped.
 *
 * Callers only need to describe which ranges are replaced and whether replacements are
 * original source slices or generated bridge code. This function handles MagicString,
 * cursor tracking, and generated-only sourcemap guard segments.
 *
 * The template and script edits this receives address disjoint SFC blocks, so an overlap is an
 * analyzer bug and is rejected rather than silently dropped. Zero-width insertions that share a
 * position with the following edit are fine, which is what the generated registration template for a
 * template-less override relies on.
 */
function applySourceEdits(source: string, filename: string, edits: SourceEdit[]): AppliedSourceEdits {
    const context: ApplySourceEditContext = {
        bundle: new Bundle({ separator: '' }),
        originalSource: new MagicString(source, { filename }),
        source,
        generatedCursor: {
            line: 1,
            column: 0,
        },
        generatedOnlyPositions: [],
    };
    let cursor = 0;

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

            appendTrackedChunk(context, {
                type: 'original',
                start: cursor,
                end: edit.start,
            });

            // No expansion pass: chunks are already flat, so each one is appended as it stands.
            normalizeReplacement(edit.replacement).forEach((chunk) => appendTrackedChunk(context, chunk));

            cursor = edit.end;
        });

    appendTrackedChunk(context, {
        type: 'original',
        start: cursor,
        end: source.length,
    });

    const generatedMap = context.bundle.generateMap({
        includeContent: true,
        hires: true,
    });
    const map = generatedMap as SourceMap;
    map.sourcesContent = generatedMap.sourcesContent?.map((content) => content ?? '') ?? [];
    markGeneratedCodeAsUnmapped(map, context.generatedOnlyPositions);

    return {
        code: context.bundle.toString(),
        map,
    };
}

/**
 * @private
 */
export { type AppliedSourceEdits, type SourceEdit, applySourceEdits };
