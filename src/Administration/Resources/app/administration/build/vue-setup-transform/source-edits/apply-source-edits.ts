/**
 * @sw-package framework
 */

import MagicString, {
    Bundle,
    type SourceMap,
} from 'magic-string';
import {
    generated,
    type FlatSourceChunk,
    type SourceChunk,
} from './chunks';
import { toFlatChunks } from './render-chunks';
import {
    type GeneratedPosition,
    findGeneratedOnlyMappingEdges,
    markGeneratedCodeAsUnmapped,
    moveGeneratedCursor,
} from './sourcemap';

type SourceEdit = {
    start: number,
    end: number,
    replacement: string | SourceChunk[],
};

type AppliedSourceEdits = {
    code: string,
    map: SourceMap,
};

type ApplySourceEditContext = {
    bundle: Bundle,
    originalSource: MagicString,
    source: string,
    generatedCursor: GeneratedPosition,
    generatedOnlyPositions: GeneratedPosition[],
};

/**
 * Adds either generated code or an original source slice to a sourcemap-aware bundle.
 */
function appendChunk(bundle: Bundle, originalSource: MagicString, chunk: FlatSourceChunk): void {
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
function appendTrackedChunk(context: ApplySourceEditContext, chunk: FlatSourceChunk): void {
    if (chunk.type === 'generated') {
        if (chunk.code.length > 0) {
            context.generatedOnlyPositions.push(
                ...findGeneratedOnlyMappingEdges(context.generatedCursor, chunk.code),
            );
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
 */
function applySourceEdits(
    source: string,
    filename: string,
    edits: SourceEdit[],
): AppliedSourceEdits {
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
                return;
            }

            appendTrackedChunk(context, {
                type: 'original',
                start: cursor,
                end: edit.start,
            });

            toFlatChunks(normalizeReplacement(edit.replacement), source).forEach((chunk) => appendTrackedChunk(context, chunk));

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

export {
    type AppliedSourceEdits,
    type SourceEdit,
    applySourceEdits,
};
