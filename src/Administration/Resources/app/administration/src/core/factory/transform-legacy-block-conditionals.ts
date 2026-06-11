/**
 * @sw-package framework
 */

import type { LegacyConditionRenderOrderSegment } from 'src/app/composables/use-block-context';

const SELF_CLOSING_TAG_REG_EXP = /<([A-Za-z][\w:-]*)(?:\s+((?:[^"'<>]|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')*?))?\s*\/>/g;
const CONDITIONAL_REG_EXP = /v-(?:if|else-if|else)\b/;

type LegacyBlockHelperNames = {
    if: string;
    elseIf: string;
    else: string;
};

type HelperParameters = {
    segmentCaseIndex: number;
    isStartingCondition?: boolean;
    renderOrderSegment: LegacyConditionRenderOrderSegment;
};

/**
 * @private
 */
export type LegacyConditionCaseReservation = {
    chainKey: string;
    caseCount: number;
    caseStartIndex: number;
};

/**
 * @private
 */
export type LegacyConditionTransformResult = {
    template: string;
    conditionCases: LegacyConditionCaseReservation[];
    trailingChainKey?: string;
};

/**
 * @private
 */
export type LegacyTwigBlockSequenceEntry = {
    blockName: string;
    innerTemplate: string;
};

/**
 * @private
 */
export type LegacyTwigBlockSequenceTransformEntry = LegacyTwigBlockSequenceEntry & {
    legacyConditionCases: LegacyConditionCaseReservation[];
};

type BlockConditionChainInfo = {
    children: Element[];
    blockName: string;
    starting: boolean; //Is this chain starting a chain in the block
    ending: boolean; //Is this chain ending a chain in the block
    firstChainInBlock: boolean;
    lastChainInBlock: boolean;
    index: number;
    fullChainKey?: string;
    caseStartIndex?: number; //Assigned during rewrite construction, the starting case index for this chain in its render-order segment
    followedBy?: BlockConditionChainInfo; //Chains in other blocks that are following this chain as continuation chains
};

type BlockConditionInfo = {
    blockName: string;
    renderOrderSegment: LegacyConditionRenderOrderSegment;
    conditionalChains: BlockConditionChainInfo[];
};

type RewriteInfo = {
    codeBefore: string;
    codeAfter: string;
};

const GLOBAL_LEGACY_HELPERS = {
    if: '$swLegacyBlockIf',
    elseIf: '$swLegacyBlockElseIf',
    else: '$swLegacyBlockElse',
} satisfies LegacyBlockHelperNames;

/** Escapes block names for helper calls embedded in single-quoted Vue expressions. */
function escapeSingleQuotedString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function createLegacyConditionOptionsCode(parameters: HelperParameters): string {
    return `{ segmentCaseIndex: ${parameters.segmentCaseIndex}, isStartingCondition: ${Boolean(parameters.isStartingCondition)}, renderOrderSegment: '${parameters.renderOrderSegment}' }`;
}

/** Builds the replacement v-if expression that links a case to the legacy condition state. */
function createLegacyHelperExpression(
    helperName: string,
    conditionKey: string,
    expression: string | null | undefined,
    parameters: HelperParameters,
): string {
    const escapedConditionKey = escapeSingleQuotedString(conditionKey);

    if (expression !== undefined) {
        return `${helperName}('${escapedConditionKey}', ${expression}, ${createLegacyConditionOptionsCode(parameters)})`;
    }

    return `${helperName}('${escapedConditionKey}', ${createLegacyConditionOptionsCode(parameters)})`;
}

/** Expands self-closing custom components so the browser parser keeps the intended tree. */
function normalizeSelfClosingTags(template: string): string {
    return template.replace(SELF_CLOSING_TAG_REG_EXP, (match, tagName: string, attributes: string = '') => {
        const trimmedAttributes = attributes.trim();
        const normalizedAttributes = trimmedAttributes.length > 0 ? ` ${trimmedAttributes}` : '';

        return `<${tagName}${normalizedAttributes}></${tagName}>`;
    });
}

function createLegacyConditionChainKey(blockName: string, chainIndex: number): string {
    return `${blockName}:${chainIndex}`;
}

/**
 * Rewrites native sw-block conditional chains before Vue compiles them.
 *
 * @private
 */
export default function transformNativeLegacyBlockConditionals(template: string): string {
    if (template.indexOf('<sw-block') === -1 || !CONDITIONAL_REG_EXP.test(template) || typeof document === 'undefined') {
        return template;
    }

    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    const blocks = parsedTemplate.content.querySelectorAll('sw-block[name], sw-block[extends]');

    const entries = Array.from(blocks).map((block) => {
        const renderOrderSegment: LegacyConditionRenderOrderSegment = block.hasAttribute('extends')
            ? 'nativeExtension'
            : 'defaultSlot';

        return {
            blockName: block.getAttribute('name') ?? block.getAttribute('extends')!,
            innerTemplate: block.innerHTML,
            renderOrderSegment,
        };
    });

    const blockConditionInfos: BlockConditionInfo[] = [];

    entries.forEach((entry) => {
        const conditionalChains = collectBlockConditionalChains(
            entry.blockName,
            parseBlockTemplateChildren(entry.innerTemplate),
        );
        blockConditionInfos.push({
            blockName: entry.blockName,
            renderOrderSegment: entry.renderOrderSegment,
            conditionalChains: conditionalChains,
        });
    });

    fillChainIndices(blockConditionInfos);

    const allRewrites: RewriteInfo[] = [];
    blockConditionInfos.forEach((blockInfo) => {
        blockInfo.conditionalChains
            .filter((chain) => shouldPerformChainRewrite(chain))
            .forEach((chain) => {
                const rewrites = constructChainAttributeRewrites(chain, template, blockInfo.renderOrderSegment);
                allRewrites.push(...rewrites);
            });
    });
    template = applyOrderedRewrites(template, allRewrites);

    return template;
}

function collectBlockConditionalChains(blockName: string, children: Element[]): BlockConditionChainInfo[] {
    let chainIndex: number = 0;
    let buildingChain = false;
    const conditionalChains: BlockConditionChainInfo[] = [];

    children.forEach((child, childIndex) => {
        const isConditional = child.hasAttribute('v-if') || child.hasAttribute('v-else-if') || child.hasAttribute('v-else');

        if (buildingChain && (!isConditional || child.hasAttribute('v-if'))) {
            buildingChain = false;
            chainIndex += 1;
        }

        if (!isConditional) {
            return;
        }

        if (buildingChain) {
            conditionalChains[chainIndex].children.push(child);

            if (child.hasAttribute('v-else')) {
                conditionalChains[chainIndex].ending = true;
                conditionalChains[chainIndex].lastChainInBlock = childIndex === children.length - 1;
                buildingChain = false;
                chainIndex += 1;
            } else if (childIndex === children.length - 1) {
                conditionalChains[chainIndex].lastChainInBlock = true;
            }

            return;
        }

        if (!child.hasAttribute('v-if')) {
            buildingChain = true;
            conditionalChains.push({
                children: [child],
                starting: false,
                ending: child.hasAttribute('v-else'),
                firstChainInBlock: childIndex === 0,
                lastChainInBlock: false,
                index: chainIndex,
                fullChainKey: createLegacyConditionChainKey(blockName, chainIndex),
                blockName: blockName,
            });
            if (child.hasAttribute('v-else')) {
                buildingChain = false;
                chainIndex += 1;
            } else if (childIndex === children.length - 1) {
                conditionalChains[chainIndex].lastChainInBlock = true;
            }

            return;
        }

        buildingChain = true;
        conditionalChains.push({
            children: [child],
            starting: true,
            ending: false,
            lastChainInBlock: false,
            firstChainInBlock: childIndex === 0,
            index: chainIndex,
            fullChainKey: createLegacyConditionChainKey(blockName, chainIndex),
            blockName: blockName,
        });

        if (childIndex === children.length - 1 && buildingChain) {
            conditionalChains[chainIndex].lastChainInBlock = true;
        }
    });

    return conditionalChains;
}

function parseBlockTemplateChildren(template: string): Element[] {
    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    return Array.from(parsedTemplate.content.children);
}

function fillChainIndices(
    blockConditionInfos: BlockConditionInfo[],
    caseStartIndexByChainKey: Record<string, number> = {},
): void {
    let lastChain: BlockConditionChainInfo | null = null;
    let startingChain: BlockConditionChainInfo | null = null;

    const nextCaseIndexByChainKey = new Map<string, number>();

    // Iterate over blocks in order and assign chain keys, ensuring that continuation chains receive the same key as their leading chain.
    blockConditionInfos.forEach((blockInfo) => {
        blockInfo.conditionalChains.forEach((chain) => {
            if (chain.starting) {
                chain.fullChainKey = createLegacyConditionChainKey(blockInfo.blockName, chain.index);
                lastChain = chain;
                startingChain = chain;
            } else if (startingChain && lastChain) {
                chain.fullChainKey = startingChain.fullChainKey;
                lastChain.followedBy = chain;
            }

            if (chain.fullChainKey) {
                const chainSegmentKey = `${chain.fullChainKey}:${blockInfo.renderOrderSegment}`;
                const caseStartIndex =
                    nextCaseIndexByChainKey.get(chainSegmentKey) ?? caseStartIndexByChainKey[chain.fullChainKey] ?? 0;
                chain.caseStartIndex = caseStartIndex;
                nextCaseIndexByChainKey.set(chainSegmentKey, caseStartIndex + chain.children.length);
            }

            if (!chain.starting) {
                if (chain.ending) {
                    lastChain = null;
                    startingChain = null;
                } else {
                    lastChain = chain;
                }
            }
        });
    });
}

function normalizeParsedVueTemplate(template: string): string {
    return template.replace(/(\s)([^\s"'<>\/=]+)\s*=\s*(?:""|'')/g, '$1$2');
}

function getAttributeCode(template: string, attributeName: string): string {
    const escapedAttributeName = attributeName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const attributeRegExp = new RegExp(`\\b${escapedAttributeName}(?![\\w-])(?:\\s*=\\s*(?:"[^"]*"|'[^']*'))?`);
    const match = template.match(attributeRegExp);

    if (!match) {
        console.warn(
            `Failed to extract code for attribute "${attributeName}" during rewrite. This should not happen.`,
            template,
        );
        return '';
    }

    return match[0] ?? '';
}

function constructChainAttributeRewrites(
    chain: BlockConditionChainInfo,
    template: string,
    renderOrderSegment: LegacyConditionRenderOrderSegment,
): RewriteInfo[] {
    const rewrites: RewriteInfo[] = [];
    const chainKey = chain.fullChainKey ?? createLegacyConditionChainKey(chain.blockName, chain.index);

    chain.children.forEach((child, localSegmentCaseIndex) => {
        const segmentCaseIndex = (chain.caseStartIndex ?? 0) + localSegmentCaseIndex;
        if (child.hasAttribute('v-if')) {
            const codeBefore = normalizeParsedVueTemplate(child.outerHTML);
            const oldExpression = getAttributeCode(codeBefore, 'v-if');
            const newExpression = `v-if="${createLegacyHelperExpression(GLOBAL_LEGACY_HELPERS.if, chainKey, child.getAttribute('v-if'), { isStartingCondition: chain.starting && localSegmentCaseIndex === 0, segmentCaseIndex, renderOrderSegment })}"`;
            rewrites.push({
                codeBefore: oldExpression,
                codeAfter: newExpression,
            });
        } else if (child.hasAttribute('v-else-if')) {
            const codeBefore = normalizeParsedVueTemplate(child.outerHTML);
            const oldExpression = getAttributeCode(codeBefore, 'v-else-if');
            const newExpression = `v-if="${createLegacyHelperExpression(GLOBAL_LEGACY_HELPERS.elseIf, chainKey, child.getAttribute('v-else-if'), { isStartingCondition: false, segmentCaseIndex, renderOrderSegment })}"`;
            rewrites.push({
                codeBefore: oldExpression,
                codeAfter: newExpression,
            });
        } else if (child.hasAttribute('v-else')) {
            const codeBefore = normalizeParsedVueTemplate(child.outerHTML);
            const oldExpression = getAttributeCode(codeBefore, 'v-else');
            const newExpression = `v-if="${createLegacyHelperExpression(GLOBAL_LEGACY_HELPERS.else, chainKey, undefined, { isStartingCondition: false, segmentCaseIndex, renderOrderSegment })}"`;
            rewrites.push({
                codeBefore: oldExpression,
                codeAfter: newExpression,
            });
        }
    });

    return rewrites;
}

function shouldPerformBlockRewrite(chains: BlockConditionChainInfo[]): boolean {
    if (chains.length === 0) {
        return false;
    }
    if (chains.every((chain) => chain.starting && chain.followedBy === undefined)) {
        return false;
    }
    return true;
}

function shouldPerformChainRewrite(chain: BlockConditionChainInfo): boolean {
    return !chain.starting || chain.followedBy !== undefined || chain.firstChainInBlock || chain.lastChainInBlock;
}

function applyOrderedRewrites(template: string, rewrites: RewriteInfo[]): string {
    let cursor = 0;
    let rewrittenTemplate = template;

    rewrites.forEach((rewrite) => {
        let foundIndex = rewrittenTemplate.indexOf(rewrite.codeBefore, cursor);

        while (
            foundIndex !== -1 &&
            rewrite.codeBefore === 'v-else' &&
            /[\w-]/.test(rewrittenTemplate.charAt(foundIndex + rewrite.codeBefore.length))
        ) {
            foundIndex = rewrittenTemplate.indexOf(rewrite.codeBefore, foundIndex + rewrite.codeBefore.length);
        }

        if (foundIndex === -1) {
            console.warn('Failed to apply rewrite because codeBefore was not found from cursor position.', {
                codeBefore: rewrite.codeBefore,
                codeAfter: rewrite.codeAfter,
                cursor,
            });
            return;
        }

        rewrittenTemplate =
            rewrittenTemplate.slice(0, foundIndex) +
            rewrite.codeAfter +
            rewrittenTemplate.slice(foundIndex + rewrite.codeBefore.length);

        cursor = foundIndex + rewrite.codeAfter.length;
    });

    return rewrittenTemplate;
}

/**
 * Rewrites neighboring top-level legacy Twig blocks as one conditional sequence.
 *
 * @private
 */
export function transformLegacyTwigBlockSequenceConditionals(
    initialEntries: LegacyTwigBlockSequenceEntry[],
    caseStartIndexByChainKey: Record<string, number> = {},
): LegacyTwigBlockSequenceTransformEntry[] {
    const blockConditonalChains: BlockConditionInfo[] = [];

    const entries: LegacyTwigBlockSequenceTransformEntry[] = initialEntries.map((entry) => ({
        ...entry,
        legacyConditionCases: [],
    }));

    // Step 1: Analyze the blocks child elements to find v-if / v-else-if / v-else chains and collect information about their structure, such as whether they are leading or trailing chains and which chains are continuing each other across blocks.
    entries.forEach((entry) => {
        const children = parseBlockTemplateChildren(entry.innerTemplate);
        const conditionalChains = collectBlockConditionalChains(entry.blockName, children);
        blockConditonalChains.push({
            blockName: entry.blockName,
            renderOrderSegment: 'shimExtension',
            conditionalChains,
        });
    });
    // Step 2: Assign stable chain keys to the collected chains, ensuring that continuation chains across blocks receive the same key as their leading chain.
    fillChainIndices(blockConditonalChains, caseStartIndexByChainKey);

    // Step 3: Apply the helper function rewrites to each chain

    entries.forEach((entry, entryIndex) => {
        const chains = blockConditonalChains[entryIndex]?.conditionalChains ?? [];
        if (shouldPerformBlockRewrite(chains)) {
            entry.legacyConditionCases.push(
                ...chains
                    .filter((chain) => shouldPerformChainRewrite(chain))
                    .map((chain) => ({
                        chainKey: chain.fullChainKey ?? createLegacyConditionChainKey(chain.blockName, chain.index),
                        caseStartIndex: chain.caseStartIndex ?? 0,
                        caseCount: chain.children.length,
                    })),
            );
            chains.forEach((chain) => {
                const rewrites = constructChainAttributeRewrites(chain, entry.innerTemplate, 'shimExtension');
                entry.innerTemplate = applyOrderedRewrites(entry.innerTemplate, rewrites);
            });
        }
    });

    return entries;
}
