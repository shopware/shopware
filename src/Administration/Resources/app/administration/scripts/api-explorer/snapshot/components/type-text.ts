/**
 * @sw-package framework
 */

import * as ts from 'typescript';
import type { AnalysisContext } from '../analysis-context';
import { digestType } from '../type-hash';
import { DEFAULT_TYPE_FLAGS, printType } from '../type-printer';

/** Longer texts are cut; a hash of the full canonical structure keeps every change in the cut part visible. */
export const MAX_TYPE_TEXT_LENGTH = 300;

/** Up to this digest length the checker's full print is cheap enough to try whether it still fits. */
const FULL_PRINT_LIMIT = 4 * MAX_TYPE_TEXT_LENGTH;

const CHEAP_FLAGS = DEFAULT_TYPE_FLAGS & ~ts.TypeFormatFlags.NoTruncation;

/** The checker's truncation markers: `... 3 more ...`, `{ ...; }`, `[...]`; a rest parameter `...args` is none. */
const TRUNCATION_MARKER = /\.\.\.(?![A-Za-z_$[{])/;

const caches = new WeakMap<AnalysisContext, Map<ts.Type, string>>();

/**
 * A truncated print shows whichever members come first in type id order, which differs between two
 * checkouts, so it is only kept when it is complete.
 */
function computeTypeText(context: AnalysisContext, type: ts.Type): string {
    const cheap = printType(context, type, undefined, CHEAP_FLAGS);

    if (cheap.length <= MAX_TYPE_TEXT_LENGTH && !TRUNCATION_MARKER.test(cheap)) {
        return cheap;
    }

    const digest = digestType(context, type);

    if (digest.length <= FULL_PRINT_LIMIT) {
        const full = printType(context, type);

        if (full.length <= MAX_TYPE_TEXT_LENGTH) {
            return full;
        }
    }

    const display = digest.display(MAX_TYPE_TEXT_LENGTH);

    return `${display}… [+${Math.max(digest.length - display.length, 0)} chars #${digest.hash.slice(0, 8)}]`;
}

export function typeText(context: AnalysisContext, type: ts.Type): string {
    let cache = caches.get(context);

    if (!cache) {
        cache = new Map();
        caches.set(context, cache);
    }

    let text = cache.get(type);

    if (text === undefined) {
        text = computeTypeText(context, type);
        cache.set(type, text);
    }

    return text;
}

export function widenedTypeText(context: AnalysisContext, type: ts.Type): string {
    const { checker } = context;

    return typeText(context, checker.getWidenedType(checker.getBaseTypeOfLiteralType(type)));
}
