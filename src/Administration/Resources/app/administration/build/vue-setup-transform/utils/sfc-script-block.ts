/**
 * @sw-package framework
 */

import type { SFCScriptBlock } from '@vue/compiler-sfc';
import { ShopwareSetupTransformError } from './transform-error';

type ScriptBlock = {
    type: 'script' | 'scriptSetup';
    start: number;
    end: number;
    contentStart: number;
    content: string;
    generatedPassthroughAttributesSource: string;
    lang: string | null;
};

type SfcAttributeValue = string | true;

function escapeAttributeValue(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

function buildGeneratedAttributesSource(
    attributes: Record<string, SfcAttributeValue>,
    {
        setup,
        fallbackLanguage,
    }: {
        setup: boolean;
        fallbackLanguage: string;
    },
): string {
    const generatedAttributes = new Map<string, SfcAttributeValue>(Object.entries(attributes));

    if (setup) {
        generatedAttributes.set('setup', true);
    } else {
        generatedAttributes.delete('setup');
    }

    if (!generatedAttributes.has('lang')) {
        generatedAttributes.set('lang', fallbackLanguage);
    }

    if (generatedAttributes.size === 0) {
        return '';
    }

    const source = Array.from(generatedAttributes.entries()).map(
        ([
            name,
            value,
        ]) => {
            if (value === true) {
                return name;
            }

            return `${name}="${escapeAttributeValue(value)}"`;
        },
    );

    return ` ${source.join(' ')}`;
}

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
        generatedPassthroughAttributesSource: buildGeneratedAttributesSource(descriptorBlock.attrs, {
            setup: type === 'scriptSetup',
            fallbackLanguage: 'ts',
        }),
        lang: descriptorBlock.lang ?? null,
    };
}

export { type ScriptBlock, toScriptBlock };
