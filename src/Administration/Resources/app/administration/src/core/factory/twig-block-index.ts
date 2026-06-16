/**
 * @sw-package framework
 * @private
 *
 * Block index for the Twig → Native Block Runtime Adapter.
 *
 * Twig block entries are parsed synchronously whenever `async-component.factory.ts`
 * processes a `Shopware.Component.override()` call.
 *
 * TwigJS is imported here for parsing only. The global TwigJS singleton is
 * already configured by `template.factory.js` (output tokens filtered,
 * `{% parent %}` tag registered) before this module is first used.
 */

import Twig from 'twig';
import reconstructInnerTemplate, { type TwigToken } from './reconstruct-twig-template';
import {
    indexLegacyTwigBlockConditionEntries,
    type LegacyTwigBlockSequenceEntry,
} from './transform-legacy-block-conditionals';

/**
 * @private
 */
export {
    getLegacyTwigBlockEntries as getBlockEntries,
    hasLegacyTwigBlockEntries as hasBlockEntries,
    resetLegacyTwigBlockConditionIndex as resetBlockIndex,
} from './transform-legacy-block-conditionals';

/**
 * @private
 */
export type { BlockEntry } from './transform-legacy-block-conditionals';

type ParsedTwigToken = {
    type: string;
    value?: string;
    token?: {
        type?: string;
        blockName?: string;
        output?: unknown[];
    };
};

type ParsedBlockToken = ParsedTwigToken & {
    token: {
        blockName: string;
        output?: unknown[];
    };
};

function isBlockToken(token: ParsedTwigToken): token is ParsedBlockToken {
    return token.type === 'logic' && typeof token.token?.blockName === 'string';
}

function parseTwigBlockEntries(componentName: string, rawTemplate: string): LegacyTwigBlockSequenceEntry[] | null {
    let parsed: ReturnType<typeof Twig.twig>;

    try {
        parsed = Twig.twig({ data: rawTemplate, rethrow: true });
    } catch (error) {
        console.warn(`[sw-block] Failed to parse Twig template for "${componentName}":`, error);
        return null;
    }

    const parsedTokens = parsed.tokens as ParsedTwigToken[];

    return parsedTokens.filter(isBlockToken).map((token) => ({
        blockName: token.token.blockName,
        innerTemplate: reconstructInnerTemplate((token.token.output ?? []) as TwigToken[]),
    }));
}

/**
 * Parses `rawTemplate` with TwigJS and indexes every top-level `{% block %}`
 * found. Called synchronously from `override()` before the template string is
 * handed to `TemplateFactory`.
 *
 * Warns and skips malformed templates — TwigJS may surface the error again
 * later through the normal template pipeline if needed.
 *
 * @private
 */
export function indexTwigBlocksFromTemplate(componentName: string, rawTemplate: string): void {
    const entries = parseTwigBlockEntries(componentName, rawTemplate);

    if (!entries) {
        return;
    }

    indexLegacyTwigBlockConditionEntries(componentName, entries);
}
