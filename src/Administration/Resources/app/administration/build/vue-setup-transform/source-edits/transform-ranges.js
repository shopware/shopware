/**
 * @sw-package framework
 */

const {
    fromSource,
    generated,
    trim,
} = require('./chunks');

/**
 * @typedef {import('./chunks').SourceChunk} SourceChunk
 */

/**
 * Removes ranges and applies generated replacements while preserving original mappings
 * for untouched source slices.
 *
 * @param {{ contentStart: number }} block
 * @param {{ source: string }} analysis
 * @param {{ start: number, end: number }[]} removals
 * @param {({ start: number, end: number } & { replacement: string })[]} [replacements]
 * @returns {SourceChunk[]}
 */
function transformRanges(block, analysis, removals, replacements = []) {
    const sortedRanges = [
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
    const chunks = [];

    sortedRanges.forEach((range) => {
        if (range.start < cursor) {
            return;
        }

        if (cursor < range.start) {
            chunks.push(fromSource(block, cursor, range.start));
        }

        if (range.replacement.length > 0) {
            chunks.push(generated(range.replacement));
        }

        cursor = range.end;
    });

    if (cursor < analysis.source.length) {
        chunks.push(fromSource(block, cursor, analysis.source.length));
    }

    return [trim(chunks)];
}

module.exports = {
    transformRanges,
};
