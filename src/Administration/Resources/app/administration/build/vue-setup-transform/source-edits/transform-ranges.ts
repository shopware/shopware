/**
 * @sw-package framework
 */

import {
    fromSource,
    generated,
    trim,
    type SourceChunk,
} from './chunks';

type SourceBlock = {
    contentStart: number,
};

type SourceAnalysis = {
    source: string,
};

type SourceRange = {
    start: number,
    end: number,
};

type SourceReplacement = SourceRange & {
    replacement: string,
};

/**
 * Removes ranges and applies generated replacements while preserving original mappings
 * for untouched source slices.
 */
function transformRanges(
    block: SourceBlock,
    analysis: SourceAnalysis,
    removals: SourceRange[],
    replacements: SourceReplacement[] = [],
): SourceChunk[] {
    const sortedRanges: SourceReplacement[] = [
        ...removals.map((range) => ({
            ...range,
            replacement: '',
        })),
        ...replacements,
    ].sort((a, b) => {
        if (a.start === b.start) {
            return b.end - a.end;
        }

        return a.start - b.start;
    });
    let cursor = 0;
    const chunks: SourceChunk[] = [];

    sortedRanges.forEach((range) => {
        if (range.start < cursor) {
            return;
        }

        if (cursor < range.start) {
            chunks.push(fromSource(block, { start: cursor, end: range.start }));
        }

        if (range.replacement.length > 0) {
            chunks.push(generated(range.replacement));
        }

        cursor = range.end;
    });

    if (cursor < analysis.source.length) {
        chunks.push(fromSource(block, { start: cursor, end: analysis.source.length }));
    }

    return [trim(chunks)];
}

module.exports = {
    transformRanges,
};

export {
    transformRanges,
};
