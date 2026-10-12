/**
 * @sw-package framework
 */

/**
 * Renders source chunks to text while preserving original/generated boundaries.
 *
 * Generated chunks carry their own code; original chunks are read back out of the SFC source, so a
 * later sourcemap pass can walk the same chunk list and map them to author positions.
 */

import type { SourceChunk } from './chunks';

/**
 * Reads the text represented by a source chunk.
 */
function getChunkText(chunk: SourceChunk, source: string, sourceOffset: number): string {
    return chunk.type === 'generated' ? chunk.code : source.slice(chunk.start - sourceOffset, chunk.end - sourceOffset);
}

/**
 * Joins chunks into the transformed text.
 */
function render(chunks: SourceChunk[], source: string, sourceOffset = 0): string {
    return chunks.map((chunk) => getChunkText(chunk, source, sourceOffset)).join('');
}

/**
 * @private
 */
export { render };
