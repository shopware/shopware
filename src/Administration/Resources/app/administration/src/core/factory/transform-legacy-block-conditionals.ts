/**
 * @sw-package framework
 */

import type { LegacyConditionRenderOrderSegment } from 'src/app/component/structure/sw-block-override/shim/legacy-condition-context';

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

/**
 * @private
 */
export type BlockEntry = {
    componentName: string;
    innerTemplate: string;
    legacyConditionCases: LegacyConditionCaseReservation[];
};

type BlockConditionChainInfo = {
    children: Element[];
    blockName: string;
    starting: boolean; //Is this chain starting a chain in the block
    ending: boolean; //Is this chain ending a chain in the block
    detachedFromPrevious?: boolean;
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
    detachedFromPrevious: boolean;
};

type RewriteInfo = {
    codeBefore: string[];
    codeAfter: string;
};

const GLOBAL_LEGACY_HELPERS = {
    if: '$swLegacyBlockIf',
    elseIf: '$swLegacyBlockElseIf',
    else: '$swLegacyBlockElse',
} satisfies LegacyBlockHelperNames;

type LegacyConditionContinuationContext = Record<string, string>;

const legacyConditionContinuationContexts = new Map<string, LegacyConditionContinuationContext>();
let legacyConditionContinuationContextVersion = 0;
const legacyTwigBlockIndex = new Map<string, BlockEntry[]>();
const indexedLegacyTwigBlockEntries: Array<{
    componentName: string;
    entries: LegacyTwigBlockSequenceEntry[];
}> = [];

let legacyTwigBlockIndexDirty = false;
let legacyTwigBlockIndexVersion = -1;

function resetLegacyConditionContinuationContexts(): void {
    legacyConditionContinuationContexts.clear();
    legacyConditionContinuationContextVersion += 1;
}

function storeLegacyConditionContinuationAlias(componentName: string, localChainKey: string, fullChainKey: string): void {
    const context = legacyConditionContinuationContexts.get(componentName) ?? {};

    if (context[localChainKey] === fullChainKey) {
        return;
    }

    context[localChainKey] = fullChainKey;
    legacyConditionContinuationContexts.set(componentName, context);
    legacyConditionContinuationContextVersion += 1;
}

function getChainKeyForRewrite(
    chain: BlockConditionChainInfo,
    renderOrderSegment: LegacyConditionRenderOrderSegment,
    continuationContext: LegacyConditionContinuationContext | undefined,
): string {
    const localChainKey = createLegacyConditionChainKey(chain.blockName, chain.index);
    const chainKey = chain.fullChainKey ?? localChainKey;

    if (renderOrderSegment !== 'shimExtension' || !continuationContext) {
        return chainKey;
    }

    return continuationContext[localChainKey] ?? chainKey;
}

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

function escapeDoubleQuotedAttributeValue(value: string): string {
    return value.replace(/"/g, '&quot;');
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
export default function transformNativeLegacyBlockConditionals(template: string, componentName?: string): string {
    if (template.indexOf('<sw-block') === -1 || !CONDITIONAL_REG_EXP.test(template) || typeof document === 'undefined') {
        return template;
    }

    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    const blocks = parsedTemplate.content.querySelectorAll('sw-block[name], sw-block[extends]');

    const entries = Array.from(blocks).map((block, index, allBlocks) => {
        const renderOrderSegment: LegacyConditionRenderOrderSegment = block.hasAttribute('extends')
            ? 'nativeExtension'
            : 'defaultSlot';
        const previousBlock = allBlocks[index - 1];

        return {
            blockName: block.getAttribute('name') ?? block.getAttribute('extends')!,
            innerTemplate: block.innerHTML,
            renderOrderSegment,
            detachedFromPrevious: previousBlock ? !hasOnlyIgnorableNodesBetweenElements(previousBlock, block) : false,
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
            detachedFromPrevious: entry.detachedFromPrevious,
        });
    });

    fillChainIndices(blockConditionInfos, {}, componentName);

    const allRewrites: RewriteInfo[] = [];
    blockConditionInfos.forEach((blockInfo) => {
        blockInfo.conditionalChains
            .filter((chain) => shouldPerformChainRewrite(chain))
            .forEach((chain) => {
                const rewrites = constructChainAttributeRewrites(chain, blockInfo.renderOrderSegment, componentName);
                allRewrites.push(...rewrites);
            });
    });
    template = applyOrderedRewrites(template, allRewrites);

    return template;
}

function isElementNode(node: Node): node is Element {
    return node.nodeType === 1;
}

function isChainBreakingTextNode(node: Node): boolean {
    return node.nodeType === 3 && (node.textContent ?? '').trim().length > 0;
}

function hasOnlyIgnorableNodesAfter(nodes: ChildNode[], currentIndex: number): boolean {
    return nodes.slice(currentIndex + 1).every((node) => !isElementNode(node) && !isChainBreakingTextNode(node));
}

function hasOnlyIgnorableNodesBetweenElements(previous: Element, current: Element): boolean {
    const commonParent = findCommonParent(previous, current);

    if (!commonParent) {
        return false;
    }

    const previousSibling = getChildBelowParent(previous, commonParent);
    const currentSibling = getChildBelowParent(current, commonParent);

    if (!previousSibling || !currentSibling) {
        return false;
    }

    if (previousSibling === currentSibling) {
        return true;
    }

    let node = previousSibling.nextSibling;

    while (node && node !== currentSibling) {
        if (isElementNode(node) || isChainBreakingTextNode(node)) {
            return false;
        }

        node = node.nextSibling;
    }

    return node === currentSibling;
}

function findCommonParent(previous: Element, current: Element): ParentNode | null {
    let parent: ParentNode | null = previous.parentNode;

    while (parent) {
        if (parent.contains(current)) {
            return parent;
        }

        parent = parent.parentNode;
    }

    return null;
}

function getChildBelowParent(element: Element, parent: ParentNode): Node | null {
    let node: Node | null = element;

    while (node?.parentNode && node.parentNode !== parent) {
        node = node.parentNode;
    }

    return node?.parentNode === parent ? node : null;
}

function collectBlockConditionalChains(blockName: string, children: ChildNode[]): BlockConditionChainInfo[] {
    let chainIndex: number = 0;
    let buildingChain = false;
    let detachedFromPrevious = false;
    const conditionalChains: BlockConditionChainInfo[] = [];

    children.forEach((node, childIndex) => {
        if (isChainBreakingTextNode(node)) {
            if (buildingChain) {
                buildingChain = false;
                chainIndex += 1;
            }
            detachedFromPrevious = true;

            return;
        }

        if (!isElementNode(node)) {
            return;
        }

        const child = node;
        const isConditional = child.hasAttribute('v-if') || child.hasAttribute('v-else-if') || child.hasAttribute('v-else');
        const isLastChainNodeInBlock = hasOnlyIgnorableNodesAfter(children, childIndex);

        if (buildingChain && (!isConditional || child.hasAttribute('v-if'))) {
            buildingChain = false;
            chainIndex += 1;
            detachedFromPrevious = true;
        }

        if (!isConditional) {
            return;
        }

        if (buildingChain) {
            conditionalChains[chainIndex].children.push(child);

            if (child.hasAttribute('v-else')) {
                conditionalChains[chainIndex].ending = true;
                conditionalChains[chainIndex].lastChainInBlock = isLastChainNodeInBlock;
                buildingChain = false;
                chainIndex += 1;
            } else if (isLastChainNodeInBlock) {
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
                detachedFromPrevious,
            });
            detachedFromPrevious = false;
            if (child.hasAttribute('v-else')) {
                buildingChain = false;
                chainIndex += 1;
            } else if (isLastChainNodeInBlock) {
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
        detachedFromPrevious = false;

        if (isLastChainNodeInBlock && buildingChain) {
            conditionalChains[chainIndex].lastChainInBlock = true;
        }
    });

    return conditionalChains;
}

function parseBlockTemplateChildren(template: string): ChildNode[] {
    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    return Array.from(parsedTemplate.content.childNodes);
}

function fillChainIndices(
    blockConditionInfos: BlockConditionInfo[],
    caseStartIndexByChainKey: Record<string, number> = {},
    componentName?: string,
): void {
    let lastChain: BlockConditionChainInfo | null = null;
    let startingChain: BlockConditionChainInfo | null = null;

    const nextCaseIndexByChainKey = new Map<string, number>();

    // Iterate over blocks in order and assign chain keys, ensuring that continuation chains receive the same key as their leading chain.
    blockConditionInfos.forEach((blockInfo) => {
        if (blockInfo.detachedFromPrevious && blockInfo.conditionalChains.length > 0) {
            lastChain = null;
            startingChain = null;
            blockInfo.conditionalChains[0].detachedFromPrevious = !blockInfo.conditionalChains[0].starting;
        }

        blockInfo.conditionalChains.forEach((chain) => {
            const localChainKey = createLegacyConditionChainKey(blockInfo.blockName, chain.index);

            if (chain.starting) {
                chain.fullChainKey = localChainKey;
                lastChain = chain;
                startingChain = chain;
            } else if (!chain.detachedFromPrevious && startingChain && lastChain) {
                chain.fullChainKey = startingChain.fullChainKey;
                chain.detachedFromPrevious = Boolean(lastChain.detachedFromPrevious);
                lastChain.followedBy = chain;
                if (
                    componentName &&
                    blockInfo.renderOrderSegment !== 'shimExtension' &&
                    chain.fullChainKey &&
                    localChainKey !== chain.fullChainKey
                ) {
                    storeLegacyConditionContinuationAlias(componentName, localChainKey, chain.fullChainKey);
                }
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

function createAttributeCodeCandidates(
    attributeName: string,
    expression: string | null,
    serializedAttribute: string,
): string[] {
    const candidates = [serializedAttribute];

    if (expression !== null) {
        candidates.push(
            `${attributeName}="${escapeDoubleQuotedAttributeValue(expression)}"`,
            `${attributeName}='${expression.replace(/'/g, '&#39;')}'`,
        );
    }

    return Array.from(new Set(candidates.filter(Boolean)));
}

function createLegacyHelperAttribute(
    helperName: string,
    chainKey: string,
    expression: string | null | undefined,
    parameters: HelperParameters,
): string {
    return `v-if="${escapeDoubleQuotedAttributeValue(createLegacyHelperExpression(helperName, chainKey, expression, parameters))}"`;
}

function addAttributeRewrite(
    rewrites: RewriteInfo[],
    child: Element,
    attributeName: string,
    helperName: string,
    chainKey: string,
    expression: string | null | undefined,
    parameters: HelperParameters,
): void {
    const codeBefore = normalizeParsedVueTemplate(child.outerHTML);
    const oldExpression = getAttributeCode(codeBefore, attributeName);

    rewrites.push({
        codeBefore: createAttributeCodeCandidates(attributeName, expression ?? null, oldExpression),
        codeAfter: createLegacyHelperAttribute(helperName, chainKey, expression, parameters),
    });
}

function constructChainAttributeRewrites(
    chain: BlockConditionChainInfo,
    renderOrderSegment: LegacyConditionRenderOrderSegment,
    componentName?: string,
): RewriteInfo[] {
    const rewrites: RewriteInfo[] = [];
    const chainKey = getChainKeyForRewrite(
        chain,
        renderOrderSegment,
        componentName ? legacyConditionContinuationContexts.get(componentName) : undefined,
    );

    chain.children.forEach((child, localSegmentCaseIndex) => {
        const segmentCaseIndex = (chain.caseStartIndex ?? 0) + localSegmentCaseIndex;
        if (child.hasAttribute('v-if')) {
            addAttributeRewrite(rewrites, child, 'v-if', GLOBAL_LEGACY_HELPERS.if, chainKey, child.getAttribute('v-if'), {
                isStartingCondition: chain.starting && localSegmentCaseIndex === 0,
                segmentCaseIndex,
                renderOrderSegment,
            });
        } else if (child.hasAttribute('v-else-if')) {
            addAttributeRewrite(
                rewrites,
                child,
                'v-else-if',
                GLOBAL_LEGACY_HELPERS.elseIf,
                chainKey,
                child.getAttribute('v-else-if'),
                {
                    isStartingCondition: false,
                    segmentCaseIndex,
                    renderOrderSegment,
                },
            );
        } else if (child.hasAttribute('v-else')) {
            addAttributeRewrite(rewrites, child, 'v-else', GLOBAL_LEGACY_HELPERS.else, chainKey, undefined, {
                isStartingCondition: false,
                segmentCaseIndex,
                renderOrderSegment,
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
    if (chain.detachedFromPrevious) {
        return false;
    }

    return !chain.starting || chain.followedBy !== undefined || chain.firstChainInBlock || chain.lastChainInBlock;
}

function findRewriteMatch(
    rewrittenTemplate: string,
    candidates: string[],
    cursor: number,
): { foundIndex: number; codeBefore: string } | null {
    return candidates.reduce<{ foundIndex: number; codeBefore: string } | null>((bestMatch, codeBefore) => {
        let foundIndex = rewrittenTemplate.indexOf(codeBefore, cursor);

        while (
            foundIndex !== -1 &&
            codeBefore === 'v-else' &&
            /[\w-]/.test(rewrittenTemplate.charAt(foundIndex + codeBefore.length))
        ) {
            foundIndex = rewrittenTemplate.indexOf(codeBefore, foundIndex + codeBefore.length);
        }

        if (foundIndex === -1) {
            return bestMatch;
        }

        if (bestMatch === null || foundIndex < bestMatch.foundIndex) {
            return { foundIndex, codeBefore };
        }

        return bestMatch;
    }, null);
}

function applyOrderedRewrites(template: string, rewrites: RewriteInfo[]): string {
    let cursor = 0;
    let rewrittenTemplate = template;

    rewrites.forEach((rewrite) => {
        const match = findRewriteMatch(rewrittenTemplate, rewrite.codeBefore, cursor);

        if (match === null) {
            console.warn('Failed to apply rewrite because codeBefore was not found from cursor position.', {
                codeBefore: rewrite.codeBefore,
                codeAfter: rewrite.codeAfter,
                cursor,
            });
            return;
        }

        rewrittenTemplate =
            rewrittenTemplate.slice(0, match.foundIndex) +
            rewrite.codeAfter +
            rewrittenTemplate.slice(match.foundIndex + match.codeBefore.length);

        cursor = match.foundIndex + rewrite.codeAfter.length;
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
    componentName: string,
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
            detachedFromPrevious: false,
        });
    });
    // Step 2: Assign stable chain keys to the collected chains, ensuring that continuation chains across blocks receive the same key as their leading chain.
    fillChainIndices(blockConditonalChains, caseStartIndexByChainKey, componentName);
    const continuationContext = legacyConditionContinuationContexts.get(componentName);

    // Step 3: Apply the helper function rewrites to each chain

    entries.forEach((entry, entryIndex) => {
        const chains = blockConditonalChains[entryIndex]?.conditionalChains ?? [];
        if (shouldPerformBlockRewrite(chains)) {
            entry.legacyConditionCases.push(
                ...chains
                    .filter((chain) => shouldPerformChainRewrite(chain))
                    .map((chain) => ({
                        chainKey: getChainKeyForRewrite(chain, 'shimExtension', continuationContext),
                        caseStartIndex: chain.caseStartIndex ?? 0,
                        caseCount: chain.children.length,
                    })),
            );
            chains
                .filter((chain) => shouldPerformChainRewrite(chain))
                .forEach((chain) => {
                    const rewrites = constructChainAttributeRewrites(chain, 'shimExtension', componentName);
                    entry.innerTemplate = applyOrderedRewrites(entry.innerTemplate, rewrites);
                });
        }
    });

    return entries;
}

function collectExistingCaseStartIndices(): Record<string, number> {
    const caseStartIndexByChainKey: Record<string, number> = {};

    legacyTwigBlockIndex.forEach((entries) => {
        entries.forEach(({ legacyConditionCases }) => {
            legacyConditionCases.forEach(({ chainKey, caseStartIndex, caseCount }) => {
                caseStartIndexByChainKey[chainKey] = Math.max(
                    caseStartIndexByChainKey[chainKey] ?? 0,
                    caseStartIndex + caseCount,
                );
            });
        });
    });

    return caseStartIndexByChainKey;
}

function ensureLegacyTwigBlockIndex(): void {
    if (!legacyTwigBlockIndexDirty && legacyTwigBlockIndexVersion === legacyConditionContinuationContextVersion) {
        return;
    }

    legacyTwigBlockIndex.clear();

    indexedLegacyTwigBlockEntries.forEach(({ componentName, entries }) => {
        const transformedEntries = transformLegacyTwigBlockSequenceConditionals(
            entries,
            componentName,
            collectExistingCaseStartIndices(),
        );

        transformedEntries.forEach((entry) => {
            const existing = legacyTwigBlockIndex.get(entry.blockName) ?? [];

            existing.push({
                componentName,
                innerTemplate: entry.innerTemplate,
                legacyConditionCases: entry.legacyConditionCases,
            });

            legacyTwigBlockIndex.set(entry.blockName, existing);
        });
    });

    legacyTwigBlockIndexDirty = false;
    legacyTwigBlockIndexVersion = legacyConditionContinuationContextVersion;
}

/**
 * @private
 */
export function indexLegacyTwigBlockConditionEntries(componentName: string, entries: LegacyTwigBlockSequenceEntry[]): void {
    indexedLegacyTwigBlockEntries.push({ componentName, entries });
    legacyTwigBlockIndexDirty = true;
}

/**
 * @private
 */
export function getLegacyTwigBlockEntries(blockName: string): BlockEntry[] {
    ensureLegacyTwigBlockIndex();

    return legacyTwigBlockIndex.get(blockName) ?? [];
}

/**
 * @private
 */
export function hasLegacyTwigBlockEntries(blockName: string): boolean {
    ensureLegacyTwigBlockIndex();

    return legacyTwigBlockIndex.has(blockName);
}

/**
 * @private
 */
export function resetLegacyTwigBlockConditionIndex(): void {
    legacyTwigBlockIndex.clear();
    indexedLegacyTwigBlockEntries.length = 0;
    legacyTwigBlockIndexDirty = false;
    legacyTwigBlockIndexVersion = -1;
    resetLegacyConditionContinuationContexts();
}
