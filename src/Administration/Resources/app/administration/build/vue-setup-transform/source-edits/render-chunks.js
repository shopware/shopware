/**
 * @sw-package framework
 */

const { generated } = require('./chunks');

/**
 * @typedef {import('./chunks').SourceChunk} SourceChunk
 */

/**
 * Reads the text represented by a flat source chunk.
 *
 * @param {SourceChunk} chunk
 * @param {string} source
 * @param {number} sourceOffset
 * @returns {string}
 */
function getChunkText(chunk, source, sourceOffset) {
    return chunk.type === 'generated' ? chunk.code : source.slice(chunk.start - sourceOffset, chunk.end - sourceOffset);
}

/**
 * Removes leading and trailing whitespace while preserving original mappings for remaining text.
 *
 * @param {SourceChunk[]} chunks
 * @param {string} source
 * @param {number} sourceOffset
 * @returns {SourceChunk[]}
 */
function renderTrim(chunks, source, sourceOffset) {
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
 * Adds indentation without turning copied original lines into generated code.
 *
 * @param {SourceChunk[]} chunks
 * @param {string} source
 * @param {number} sourceOffset
 * @param {number} spaces
 * @returns {SourceChunk[]}
 */
function renderIndent(chunks, source, sourceOffset, spaces) {
    const indentation = ' '.repeat(spaces);
    const result = [];
    let atLineStart = true;

    chunks.forEach((chunk) => {
        const code = getChunkText(chunk, source, sourceOffset);
        let offset = 0;

        code.split('\n').forEach((part, index, parts) => {
            if (part.length > 0) {
                if (atLineStart) {
                    result.push(generated(indentation));
                    atLineStart = false;
                }

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
 * @param {SourceChunk[]} chunks
 * @param {string} source
 * @param {number} [sourceOffset]
 * @returns {SourceChunk[]}
 */
function toFlatChunks(chunks, source, sourceOffset = 0) {
    return chunks.flatMap((chunk) => {
        if (chunk.type === 'generated' || chunk.type === 'original') {
            return [chunk];
        }

        const flatChunks = toFlatChunks(chunk.chunks, source, sourceOffset);

        if (chunk.type === 'trim') {
            return renderTrim(flatChunks, source, sourceOffset);
        }

        return renderIndent(flatChunks, source, sourceOffset, chunk.spaces);
    });
}

/**
 * Renders chunks for string-based assertions and generated wrapper assembly.
 *
 * @param {SourceChunk[]} chunks
 * @param {string} source
 * @param {number} [sourceOffset]
 * @returns {string}
 */
function render(chunks, source, sourceOffset = 0) {
    return toFlatChunks(chunks, source, sourceOffset)
        .map((chunk) => getChunkText(chunk, source, sourceOffset))
        .join('');
}

module.exports = {
    render,
    toFlatChunks,
};
