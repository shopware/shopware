/**
 * @sw-package framework
 */

/**
 * Converts analyzer removal/replacement ranges into source chunks for one script block.
 *
 * Lowerers use this to keep untouched author code mapped as original chunks while generated setup
 * input replacements and removed macro markers stay compiler-owned.
 */

import { ShopwareSetupTransformError } from '../utils/transform-error';
import { fromSource, generated, type SourceBlock, type SourceChunk } from './chunks';
import type { SourceRange } from '../utils/source-range';

type SourceReplacement = SourceRange & {
    replacement: string;
};

/**
 * Removes ranges and applies generated replacements while preserving original mappings
 * for untouched source slices.
 *
 * Ranges fully contained in an already-consumed range are skipped by design: a rename edit inside a
 * removed marker statement is moot, e.g. the `count` in `swDefinePublic({ count })`. A range that
 * only *partially* overlaps has no coherent meaning, so it is rejected rather than silently dropped.
 */
function transformRanges(
    block: SourceBlock,
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
            // Contained in the range already consumed - nothing left to edit.
            if (range.end <= cursor) {
                return;
            }

            throw new ShopwareSetupTransformError(
                `Partially overlapping Shopware setup source edits at ${range.start}-${range.end}: the range crosses the ` +
                    `boundary of an edit that ends at ${cursor}. This is an analyzer bug, not an authoring error.`,
                range.start,
            );
        }

        if (cursor < range.start) {
            chunks.push(fromSource(block, { start: cursor, end: range.start }));
        }

        if (range.replacement.length > 0) {
            chunks.push(generated(range.replacement));
        }

        cursor = range.end;
    });

    if (cursor < block.content.length) {
        chunks.push(fromSource(block, { start: cursor, end: block.content.length }));
    }

    return chunks;
}

/**
 * @private
 */
export { transformRanges };
