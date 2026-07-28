/**
 * @sw-package framework
 */

/**
 * Defines the small source-chunk IR used by the setup transform.
 *
 * Generated chunks contain compiler-owned text, original chunks point back into the SFC, and wrapper
 * chunks delay indentation/trimming until the renderer has access to source text. Keeping this
 * distinction lets the transform preserve author ranges when sourcemaps are added.
 */

import type { SourceRange } from '../utils/source-range';

/** Compiler-owned code with no source location in the original SFC. */
type GeneratedChunk = { type: 'generated'; code: string };

/** Absolute source slice copied from the original SFC. */
type OriginalChunk = { type: 'original'; start: number; end: number };

/** Deferred indentation wrapper around generated and original chunks. */
type IndentChunk = { type: 'indent'; chunks: SourceChunk[]; spaces: number };

/** Deferred trim wrapper that keeps remaining original ranges intact. */
type TrimChunk = { type: 'trim'; chunks: SourceChunk[] };

/** Chunk variant that can be rendered without another source-aware expansion pass. */
type FlatSourceChunk = GeneratedChunk | OriginalChunk;

/** Recursive chunk tree produced by lowerers before rendering. */
type SourceChunk = FlatSourceChunk | IndentChunk | TrimChunk;

/**
 * The SFC block an original chunk is copied from. Every caller passes a full `ShopwareSetupBlock`;
 * `fromSource` only reads `contentStart`, but keeping `content` here lets `transform-ranges` share the
 * exact same type instead of re-declaring a wider one.
 */
type SourceBlock = {
    contentStart: number;
    content: string;
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

/**
 * @private
 */
export {
    type FlatSourceChunk,
    type GeneratedChunk,
    type IndentChunk,
    type OriginalChunk,
    type SourceBlock,
    type SourceChunk,
    type TrimChunk,
    fromSource,
    generated,
    indent,
    trim,
};
