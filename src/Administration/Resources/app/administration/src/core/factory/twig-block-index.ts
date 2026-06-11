/**
 * @sw-package framework
 * @private
 *
 * Block index for the Twig → Native Block Runtime Adapter.
 *
 * Populated synchronously whenever `async-component.factory.ts` processes a
 * `Shopware.Component.override()` call that carries a Twig template string.
 * At render time `sw-block` resolves entries from this prebuilt Map and injects
 * the shim slots without any additional Twig parsing on the hot path.
 *
 * TwigJS is imported here for parsing only. The global TwigJS singleton is
 * already configured by `template.factory.js` (output tokens filtered,
 * `{% parent %}` tag registered) before this module is first used.
 */

import Twig from 'twig';
import reconstructInnerTemplate, { type TwigToken } from './reconstruct-twig-template';
import { transformLegacyTwigBlockSequenceConditionals } from './transform-legacy-block-conditionals';

/**
 * @private
 */
export type LegacyConditionCaseReservation = {
    chainKey: string;
    caseStartIndex: number;
    caseCount: number;
};

/**
 * @private
 */
export interface BlockEntry {
    componentName: string;
    innerTemplate: string;
    legacyConditionCases: LegacyConditionCaseReservation[];
}

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

const blockIndex = new Map<string, BlockEntry[]>();

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
    let parsed: ReturnType<typeof Twig.twig>;

    try {
        parsed = Twig.twig({ data: rawTemplate, rethrow: true });
    } catch (error) {
        console.warn(`[sw-block] Failed to parse Twig template for "${componentName}":`, error);
        return;
    }

    const parsedTokens = parsed.tokens as ParsedTwigToken[];

    const entries = parsedTokens.filter(isBlockToken).map((token) => ({
        blockName: token.token.blockName,
        innerTemplate: reconstructInnerTemplate((token.token.output ?? []) as TwigToken[]),
    }));

    const caseStartIndexByChainKey: Record<string, number> = {};
    entries.forEach(({ blockName }) => {
        getBlockEntries(blockName).forEach(({ legacyConditionCases }) => {
            legacyConditionCases.forEach(({ chainKey, caseStartIndex, caseCount }) => {
                caseStartIndexByChainKey[chainKey] = Math.max(
                    caseStartIndexByChainKey[chainKey] ?? 0,
                    caseStartIndex + caseCount,
                );
            });
        });
    });

    const transformedEntries = transformLegacyTwigBlockSequenceConditionals(entries, caseStartIndexByChainKey);

    transformedEntries.forEach((entry) => {
        const existing = getBlockEntries(entry.blockName);

        existing.push({
            componentName,
            innerTemplate: entry.innerTemplate,
            legacyConditionCases: entry.legacyConditionCases,
        });

        blockIndex.set(entry.blockName, existing);
    });
}

/**
 * @private
 */
export function getBlockEntries(blockName: string): BlockEntry[] {
    return blockIndex.get(blockName) ?? [];
}

/**
 * @private
 */
export function hasBlockEntries(blockName: string): boolean {
    return blockIndex.has(blockName);
}

/**
 * Clears the block index. Exposed for test teardown only — do not call in
 * production code.
 *
 * @private
 */
export function resetBlockIndex(): void {
    blockIndex.clear();
}
