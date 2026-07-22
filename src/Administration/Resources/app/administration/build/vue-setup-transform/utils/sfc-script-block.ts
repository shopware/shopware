/**
 * @sw-package framework
 */

/**
 * Reconstructs SFC script block source boundaries missing from Vue's descriptor shape.
 *
 * Vue reports content offsets, but the transform must replace the whole script block including its
 * opening tag. This module finds that tag and exposes a shared block model for normalization.
 */

import type { SFCScriptBlock } from '@vue/compiler-sfc';
import { ShopwareSetupTransformError } from './transform-error';

/**
 * Describes a script block with both full-tag and content-only source boundaries.
 *
 * `start` and `end` cover the complete SFC block replacement range, while `contentStart` anchors
 * analyzer ranges that are relative to the script body.
 */
type ScriptBlock = {
    type: 'script' | 'scriptSetup';
    start: number;
    end: number;
    contentStart: number;
    content: string;
    openingTagSource: string;
    lang: string | null;
};

/**
 * Verifies that a possible `<script` token is the tag Vue parsed, not text inside an attribute.
 */
function endsAtVueContentStart(source: string, scriptStart: number, contentStart: number): boolean {
    let quote: '"' | "'" | null = null;
    const expectedTagEnd = contentStart - 1;

    for (let index = scriptStart + '<script'.length; index <= expectedTagEnd; index += 1) {
        const character = source[index];

        if (quote) {
            if (character === quote) {
                quote = null;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;
            continue;
        }

        if (character === '>') {
            return index === expectedTagEnd;
        }
    }

    return false;
}

/**
 * Finds the full opening tag start because Vue only exposes the script content offset.
 */
function findScriptStart(source: string, contentStart: number): number {
    for (
        let index = source.lastIndexOf('<script', contentStart);
        index !== -1;
        index = source.lastIndexOf('<script', index - 1)
    ) {
        if (/^<script\b/i.test(source.slice(index)) && endsAtVueContentStart(source, index, contentStart)) {
            return index;
        }
    }

    throw new ShopwareSetupTransformError('Unable to locate Vue SFC <script> start tag.', contentStart);
}

/**
 * Builds the shared block shape consumed by semantic normalization and lowering.
 */
function toScriptBlock(source: string, descriptorBlock: SFCScriptBlock, type: ScriptBlock['type']): ScriptBlock {
    const contentStart = descriptorBlock.loc.start.offset;
    const start = findScriptStart(source, contentStart);
    const end = source.indexOf('>', descriptorBlock.loc.end.offset) + 1;

    return {
        type,
        start,
        end,
        contentStart,
        content: descriptorBlock.content,
        openingTagSource: source.slice(start, contentStart),
        lang: descriptorBlock.lang ?? null,
    };
}

export { type ScriptBlock, toScriptBlock };
