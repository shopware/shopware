/**
 * @sw-package framework
 */

/**
 * Renders source chunks while preserving original/generated boundaries.
 *
 * The lowerers compose nested trim and indent wrappers around copied source. This renderer expands
 * those wrappers into flat chunks before joining text so later sourcemap support can reuse the same
 * flattened representation.
 */

import { generated, type FlatSourceChunk, type SourceChunk } from './chunks';

/**
 * Reads the text represented by a flat source chunk.
 */
function getChunkText(chunk: FlatSourceChunk, source: string, sourceOffset: number): string {
    return chunk.type === 'generated' ? chunk.code : source.slice(chunk.start - sourceOffset, chunk.end - sourceOffset);
}

/**
 * Removes leading and trailing whitespace while preserving original mappings for remaining text.
 */
function renderTrim(chunks: FlatSourceChunk[], source: string, sourceOffset: number): FlatSourceChunk[] {
    let start = 0;
    let end = chunks.length;
    let trimStart = 0;
    let trimEnd = 0;

    while (start < end) {
        const code = getChunkText(chunks[start], source, sourceOffset);
        const trimmed = code.replace(/^\s+/u, '');

        if (trimmed.length > 0) {
            trimStart = code.length - trimmed.length;
            break;
        }

        start += 1;
    }

    while (end > start) {
        const code = getChunkText(chunks[end - 1], source, sourceOffset);
        const trimmed = code.replace(/\s+$/u, '');

        if (trimmed.length > 0) {
            trimEnd = code.length - trimmed.length;
            break;
        }

        end -= 1;
    }

    return chunks.slice(start, end).map((chunk, index, list) => {
        const isFirst = index === 0;
        const isLast = index === list.length - 1;

        if (!isFirst && !isLast) {
            return chunk;
        }

        if (chunk.type === 'generated') {
            return {
                ...chunk,
                code: chunk.code.slice(isFirst ? trimStart : 0, isLast ? chunk.code.length - trimEnd : chunk.code.length),
            };
        }

        return {
            ...chunk,
            start: chunk.start + (isFirst ? trimStart : 0),
            end: chunk.end - (isLast ? trimEnd : 0),
        };
    });
}

/**
 * Whether an absolute source position falls inside a protected range (e.g. a template literal).
 *
 * The opening line of the literal starts before the range (the `const x = ` part), so it is not
 * matched and stays indented; only continuation lines, whose start sits strictly inside the range, are
 * matched and left un-indented so the literal's runtime contents are preserved verbatim.
 */
function isInsideProtectedRange(position: number, protectedRanges: readonly (readonly [number, number])[]): boolean {
    return protectedRanges.some(
        ([
            start,
            end,
        ]) => start < position && position < end,
    );
}

/**
 * Adds indentation without turning copied original lines into generated code.
 */
function renderIndent(
    chunks: FlatSourceChunk[],
    source: string,
    sourceOffset: number,
    spaces: number,
    protectedRanges: readonly (readonly [number, number])[],
): FlatSourceChunk[] {
    const indentation = ' '.repeat(spaces);
    const result: FlatSourceChunk[] = [];
    let atLineStart = true;

    chunks.forEach((chunk) => {
        const code = getChunkText(chunk, source, sourceOffset);
        let offset = 0;

        code.split('\n').forEach((part, index, parts) => {
            if (part.length > 0) {
                // A continuation line inside a protected range (a multi-line template literal) keeps its
                // original column: indenting it would rewrite the runtime string contents.
                const lineStartIsProtected =
                    chunk.type === 'original' && isInsideProtectedRange(chunk.start + offset, protectedRanges);

                if (atLineStart && !lineStartIsProtected) {
                    result.push(generated(indentation));
                }

                atLineStart = false;

                if (chunk.type === 'generated') {
                    result.push(generated(part));
                } else {
                    result.push({
                        type: 'original',
                        start: chunk.start + offset,
                        end: chunk.start + offset + part.length,
                    });
                }
            }

            offset += part.length;

            if (index < parts.length - 1) {
                if (chunk.type === 'generated') {
                    result.push(generated('\n'));
                } else {
                    result.push({
                        type: 'original',
                        start: chunk.start + offset,
                        end: chunk.start + offset + 1,
                    });
                }

                offset += 1;
                atLineStart = true;
            }
        });
    });

    return result;
}

/**
 * Expands wrapper chunks into flat generated/original chunks once source text is available.
 *
 * `protectedRanges` are absolute source ranges (template literals) whose interior lines must not be
 * re-indented; they are only consumed by the indent expansion.
 */
function toFlatChunks(
    chunks: SourceChunk[],
    source: string,
    sourceOffset = 0,
    protectedRanges: readonly (readonly [number, number])[] = [],
): FlatSourceChunk[] {
    return chunks.flatMap((chunk) => {
        if (chunk.type === 'generated' || chunk.type === 'original') {
            return [chunk];
        }

        const flatChunks = toFlatChunks(chunk.chunks, source, sourceOffset, protectedRanges);

        if (chunk.type === 'trim') {
            return renderTrim(flatChunks, source, sourceOffset);
        }

        return renderIndent(flatChunks, source, sourceOffset, chunk.spaces, protectedRanges);
    });
}

/**
 * Renders chunks for string-based assertions and generated wrapper assembly.
 */
function render(
    chunks: SourceChunk[],
    source: string,
    sourceOffset = 0,
    protectedRanges: readonly (readonly [number, number])[] = [],
): string {
    return toFlatChunks(chunks, source, sourceOffset, protectedRanges)
        .map((chunk) => getChunkText(chunk, source, sourceOffset))
        .join('');
}

/**
 * @private
 */
export { render, toFlatChunks };
