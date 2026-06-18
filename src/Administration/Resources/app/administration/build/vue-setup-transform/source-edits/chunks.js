/**
 * @sw-package framework
 */

/**
 * @typedef {{ type: 'generated', code: string } | { type: 'original', start: number, end: number } | { type: 'indent', chunks: SourceChunk[], spaces: number } | { type: 'trim', chunks: SourceChunk[] }} SourceChunk
 */

/**
 * Creates generated code that has no honest source position.
 *
 * @param {string} code
 * @returns {SourceChunk}
 */
function generated(code) {
    return {
        type: 'generated',
        code,
    };
}

/**
 * Reuses a range from the original SFC so sourcemaps keep pointing to author code.
 *
 * @param {{ contentStart: number }} block
 * @param {number} start
 * @param {number} end
 * @returns {SourceChunk}
 */
function fromSource(block, start, end) {
    return {
        type: 'original',
        start: block.contentStart + start,
        end: block.contentStart + end,
    };
}

/**
 * Marks chunks for whitespace trimming once source text is available.
 *
 * @param {SourceChunk[]} chunks
 * @returns {SourceChunk}
 */
function trim(chunks) {
    return {
        type: 'trim',
        chunks,
    };
}

/**
 * Marks chunks for indentation once source text is available.
 *
 * @param {SourceChunk[]} chunks
 * @param {number} [spaces]
 * @returns {SourceChunk}
 */
function indent(chunks, spaces = 4) {
    return {
        type: 'indent',
        chunks,
        spaces,
    };
}

module.exports = {
    fromSource,
    generated,
    indent,
    trim,
};
