/**
 * @sw-package framework
 */

import type { SFCScriptBlock } from '@vue/compiler-sfc';
import { AttributeParser } from './attribute-parser';
import type { Attributes } from './attributes';
import { ShopwareSetupTransformError } from './transform-error';

type ScriptBlock = {
    type: 'script' | 'scriptSetup',
    start: number,
    end: number,
    contentStart: number,
    content: string,
    attributes: Attributes,
    passthroughAttributesSource: string,
    generatedPassthroughAttributesSource: string,
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
    const attributes = AttributeParser.parse(
        source.slice(start + '<script'.length, contentStart - 1),
        start + '<script'.length,
    );

    return {
        type,
        start,
        end,
        contentStart,
        content: descriptorBlock.content,
        attributes,
        passthroughAttributesSource: attributes.toSourceWithout([
            'sw-component',
            'sw-override',
        ]),
        generatedPassthroughAttributesSource: attributes.toSourceWithoutEnsuringLanguage(
            [
                'sw-component',
                'sw-override',
            ],
            'ts',
        ),
    };
}

module.exports = {
    toScriptBlock,
};

export {
    type ScriptBlock,
    toScriptBlock,
};
