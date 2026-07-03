/**
 * @sw-package framework
 */

export type GeneratedChunk = { type: 'generated'; code: string };
export type OriginalChunk = { type: 'original'; start: number; end: number };
export type IndentChunk = { type: 'indent'; chunks: SourceChunk[]; spaces: number };
export type TrimChunk = { type: 'trim'; chunks: SourceChunk[] };
export type FlatSourceChunk = GeneratedChunk | OriginalChunk;
export type SourceChunk = FlatSourceChunk | IndentChunk | TrimChunk;

type SourceBlock = {
    contentStart: number;
};

type SourceRange = {
    start: number;
    end: number;
};

/**
 * Creates generated code that has no honest source position.
 */
function generated(code: string): GeneratedChunk {
    return {
        type: 'generated',
        code,
    };
}

/**
 * Reuses a range from the original SFC so sourcemaps keep pointing to author code.
 */
function fromSource(block: SourceBlock, range: SourceRange): OriginalChunk {
    return {
        type: 'original',
        start: block.contentStart + range.start,
        end: block.contentStart + range.end,
    };
}

/**
 * Marks chunks for whitespace trimming once source text is available.
 */
function trim(chunks: SourceChunk[]): TrimChunk {
    return {
        type: 'trim',
        chunks,
    };
}

/**
 * Marks chunks for indentation once source text is available.
 */
function indent(chunks: SourceChunk[], spaces = 4): IndentChunk {
    return {
        type: 'indent',
        chunks,
        spaces,
    };
}

export { fromSource, generated, indent, trim };
