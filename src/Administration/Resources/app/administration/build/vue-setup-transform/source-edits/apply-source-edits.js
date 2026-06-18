/**
 * @sw-package framework
 */

const MagicString = require('magic-string');
const { generated } = require('./chunks');
const { toFlatChunks } = require('./render-chunks');
const {
    findGeneratedOnlyMappingEdges,
    markGeneratedCodeAsUnmapped,
    moveGeneratedCursor,
} = require('./sourcemap');

/**
 * @typedef {import('./chunks').SourceChunk} SourceChunk
 * @typedef {{ start: number, end: number, replacement: string | SourceChunk[] }} SourceEdit
 */

/**
 * Adds either generated code or an original source slice to a sourcemap-aware bundle.
 *
 * @param {import('magic-string').Bundle} bundle
 * @param {MagicString} originalSource
 * @param {SourceChunk} chunk
 * @returns {void}
 */
function appendChunk(bundle, originalSource, chunk) {
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
 *
 * @param {object} context
 * @param {import('magic-string').Bundle} context.bundle
 * @param {MagicString} context.originalSource
 * @param {string} context.source
 * @param {{ line: number, column: number }} context.generatedCursor
 * @param {{ line: number, column: number }[]} context.generatedOnlyPositions
 * @param {SourceChunk} chunk
 * @returns {void}
 */
function appendTrackedChunk(context, chunk) {
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
 *
 * @param {string | SourceChunk[]} replacement
 * @returns {SourceChunk[]}
 */
function normalizeReplacement(replacement) {
    return typeof replacement === 'string' ? [generated(replacement)] : replacement;
}

/**
 * Applies non-overlapping source edits while keeping original chunks mapped.
 *
 * Callers only need to describe which ranges are replaced and whether replacements are
 * original source slices or generated bridge code. This function handles MagicString,
 * cursor tracking, and generated-only sourcemap guard segments.
 *
 * @param {string} source
 * @param {string} filename
 * @param {SourceEdit[]} edits
 * @returns {{ code: string, map: import('magic-string').SourceMap }}
 */
function applySourceEdits(source, filename, edits) {
    const context = {
        bundle: new MagicString.Bundle({ separator: '' }),
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

    const map = context.bundle.generateMap({
        includeContent: true,
        hires: true,
    });
    markGeneratedCodeAsUnmapped(map, context.generatedOnlyPositions);

    return {
        code: context.bundle.toString(),
        map,
    };
}

module.exports = {
    applySourceEdits,
};
