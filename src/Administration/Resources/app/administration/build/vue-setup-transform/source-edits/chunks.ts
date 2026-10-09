/**
 * @sw-package framework
 */

/**
 * Defines the small source-chunk IR used by the setup transform.
 *
 * Generated chunks contain compiler-owned text, original chunks point back into the SFC. Keeping this
 * distinction lets the transform preserve author ranges when sourcemaps are added.
 *
 * There is deliberately no re-indent or trim wrapper: the transform does not beautify its output. Vue
 * compiles it next and a developer reads the author's source through the sourcemap, so moving copied
 * lines around would cost mapping fidelity and buy nothing.
 */

import type { SourceRange } from '../utils/source-range';

/** Compiler-owned code with no source location in the original SFC. */
type GeneratedChunk = { type: 'generated'; code: string };

/** Absolute source slice copied from the original SFC. */
type OriginalChunk = { type: 'original'; start: number; end: number };

/** Every chunk renders directly; there is no source-aware expansion pass. */
type SourceChunk = GeneratedChunk | OriginalChunk;

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
 * @private
 */
export { type GeneratedChunk, type OriginalChunk, type SourceBlock, type SourceChunk, fromSource, generated };
