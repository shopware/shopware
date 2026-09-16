/**
 * @sw-package framework
 */

import type { LegacyConditionRenderOrderSegment } from 'src/app/component/structure/sw-block-override/shim/legacy-condition-context';

const SELF_CLOSING_TAG_REG_EXP = /<([A-Za-z][\w:-]*)(?:\s+((?:[^"'<>]|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')*?))?\s*\/>/g;
const CONDITIONAL_REG_EXP = /v-(?:if|else-if|else)\b/;

/**
 * Maps each Vue condition case to the global helper name that should evaluate it.
 * Use it when constructing rewrite expressions for `v-if`, `v-else-if`, and `v-else`.
 *
 * @example
 * const helpers: LegacyBlockHelperNames = GLOBAL_LEGACY_HELPERS;
 */
type LegacyBlockHelperNames = {
    if: string;
    elseIf: string;
    else: string;
};

/**
 * Carries the runtime metadata inserted into each generated legacy helper call.
 * Use it when serializing helper options for a condition case in a render-order segment.
 *
 * @example
 * const parameters: HelperParameters = {
 *     segmentCaseIndex: 0,
 *     renderOrderSegment: 'shimExtension',
 * };
 */
type HelperParameters = {
    segmentCaseIndex: number;
    isStartingCondition?: boolean;
    renderOrderSegment: LegacyConditionRenderOrderSegment;
};

/**
 * Describes a transformed condition chain range that a shim slot must reserve.
 * Use it when passing metadata from the template rewrite step to `createShimSlot`.
 *
 * @example
 * const reservation: LegacyConditionCaseReservation = {
 *     chainKey: 'sw_product_detail:0',
 *     caseStartIndex: 1,
 *     caseCount: 2,
 * };
 *
 * @private
 */
export type LegacyConditionCaseReservation = {
    chainKey: string;
    caseCount: number;
    caseStartIndex: number;
    startsChain?: boolean;
};

/**
 * Represents the result of rewriting one block template string.
 * Use it when a caller needs both the rewritten template and the reservation metadata.
 *
 * @example
 * const result: LegacyConditionTransformResult = {
 *     template,
 *     conditionCases: [],
 * };
 *
 * @private
 */
export type LegacyConditionTransformResult = {
    template: string;
    conditionCases: LegacyConditionCaseReservation[];
    trailingChainKey?: string;
};

/**
 * Stores a single top-level Twig block extracted from an override template.
 * Use it as the input shape before condition chains are analyzed and rewritten.
 *
 * @example
 * const entry: LegacyTwigBlockSequenceEntry = {
 *     blockName: 'sw_product_detail_base',
 *     innerTemplate: '<div v-if="active"></div>',
 * };
 *
 * @private
 */
export type LegacyTwigBlockSequenceEntry = {
    blockName: string;
    innerTemplate: string;
};

/**
 * Stores a Twig block after condition rewriting and reservation collection.
 * Use it between the transform step and the block index that powers shim slots.
 *
 * @example
 * const entry: LegacyTwigBlockSequenceTransformEntry = {
 *     blockName: 'sw_product_detail_base',
 *     innerTemplate,
 *     legacyConditionCases: [],
 * };
 *
 * @private
 */
export type LegacyTwigBlockSequenceTransformEntry = LegacyTwigBlockSequenceEntry & {
    legacyConditionCases: LegacyConditionCaseReservation[];
};

/**
 * Describes one indexed legacy Twig override ready to become a shim slot.
 * Use it from `sw-block` when creating shim slots for a block name.
 *
 * @example
 * const blockEntry: BlockEntry = {
 *     componentName: 'sw-product-detail',
 *     innerTemplate,
 *     legacyConditionCases: [],
 * };
 *
 * @private
 */
export type BlockEntry = {
    componentName: string;
    innerTemplate: string;
    legacyConditionCases: LegacyConditionCaseReservation[];
};

/**
 * Captures one contiguous Vue condition chain found among a block's top-level children.
 * Use it while deciding whether a chain starts locally or continues across neighboring blocks.
 *
 * @example
 * const firstChild = chain.children[0];
 */
type BlockConditionChainInfo = {
    children: Element[];
    blockName: string;
    starting: boolean; // Is this chain starting a chain in the block
    ending: boolean; // Is this chain ending a chain in the block
    firstChainInBlock: boolean;
    lastChainInBlock: boolean;
    index: number;
    fullChainKey?: string;
    // Assigned during rewrite construction.
    // This is the starting case index for this chain in its render-order segment.
    caseStartIndex?: number;
    followedBy?: BlockConditionChainInfo; // Chains in other blocks that are following this chain as continuation chains
};

/**
 * Groups all condition chains discovered for one block and its render-order segment.
 * Use it as the unit passed through chain-key assignment and rewrite construction.
 *
 * @example
 * const info: BlockConditionInfo = {
 *     blockName: 'sw_product_detail_base',
 *     renderOrderSegment: 'defaultSlot',
 *     conditionalChains: [],
 * };
 */
type BlockConditionInfo = {
    blockName: string;
    renderOrderSegment: LegacyConditionRenderOrderSegment;
    conditionalChains: BlockConditionChainInfo[];
    searchStart?: number;
};

/**
 * Describes one string replacement that swaps a Vue condition attribute for a helper call.
 * Use it when applying ordered rewrites back to the original template source.
 *
 * @example
 * const rewrite: RewriteInfo = { codeBefore: ['v-else'], codeAfter: 'v-if="$swLegacyBlockElse(...)"' };
 */
type RewriteInfo = {
    codeBefore: string[];
    codeAfter: string;
    searchStart?: number;
};

const GLOBAL_LEGACY_HELPERS = {
    if: '$swLegacyBlockIf',
    elseIf: '$swLegacyBlockElseIf',
    else: '$swLegacyBlockElse',
} satisfies LegacyBlockHelperNames;

/**
 * Maps a local chain key to the full chain key discovered in a later rewrite pass.
 * Use it when native block rewrites must continue a chain that was first indexed by a Twig shim.
 *
 * @example
 * const context: LegacyConditionContinuationContext = { 'child:0': 'parent:0' };
 */
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

/**
 * Clears cross-pass aliases between local and full condition chain keys.
 * Use it during test teardown or when rebuilding the legacy Twig block index from scratch.
 *
 * @example
 * resetLegacyConditionContinuationContexts();
 */
function resetLegacyConditionContinuationContexts(): void {
    legacyConditionContinuationContexts.clear();
    legacyConditionContinuationContextVersion += 1;
}

/**
 * Stores an alias from a block-local chain key to the full key of the chain it continues.
 * Use it when native block rewrites discover that a local `v-else` continues a chain from another block.
 *
 * @example
 * storeLegacyConditionContinuationAlias('sw-product-detail', 'extension:0', 'base:0');
 */
function storeLegacyConditionContinuationAlias(componentName: string, localChainKey: string, fullChainKey: string): void {
    const context = legacyConditionContinuationContexts.get(componentName) ?? {};

    if (context[localChainKey] === fullChainKey) {
        return;
    }

    context[localChainKey] = fullChainKey;
    legacyConditionContinuationContexts.set(componentName, context);
    legacyConditionContinuationContextVersion += 1;
}

/**
 * Resolves the chain key that should be written into generated helper calls.
 * Use it when shim rewrites may need aliases collected during native-block analysis.
 *
 * @example
 * const chainKey = getChainKeyForRewrite(chain, 'shimExtension', continuationContext);
 */
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

/**
 * Escapes values for helper calls embedded in single-quoted Vue expressions.
 * Use it for chain keys before serializing them into generated template code.
 *
 * @example
 * escapeSingleQuotedString("product's-tab");
 */
function escapeSingleQuotedString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

/**
 * Serializes helper metadata into object-literal code that Vue can compile.
 * Use it when building the replacement `v-if` expression for a transformed condition.
 *
 * @example
 * createLegacyConditionOptionsCode({ segmentCaseIndex: 0, renderOrderSegment: 'defaultSlot' });
 */
function createLegacyConditionOptionsCode(parameters: HelperParameters): string {
    return [
        `{ segmentCaseIndex: ${parameters.segmentCaseIndex},`,
        `isStartingCondition: ${Boolean(parameters.isStartingCondition)},`,
        `renderOrderSegment: '${parameters.renderOrderSegment}' }`,
    ].join(' ');
}

/**
 * Builds the replacement `v-if` expression that links a case to legacy condition state.
 * Use it for every rewritten `v-if`, `v-else-if`, or `v-else` attribute.
 *
 * @example
 * createLegacyHelperExpression('$swLegacyBlockIf', 'sw_card:0', 'isVisible', parameters);
 */
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

/**
 * Escapes double quotes for generated attribute values.
 * Use it before embedding helper expressions into a double-quoted `v-if` attribute.
 *
 * @example
 * escapeDoubleQuotedAttributeValue('title === "active"');
 */
function escapeDoubleQuotedAttributeValue(value: string): string {
    return value.replace(/"/g, '&quot;');
}

/**
 * Expands self-closing custom components so the browser parser keeps the intended tree.
 * Use it before parsing templates with `template.innerHTML`.
 *
 * @example
 * normalizeSelfClosingTags('<sw-field />');
 */
function normalizeSelfClosingTags(template: string): string {
    return template.replace(SELF_CLOSING_TAG_REG_EXP, (match, tagName: string, attributes: string = '') => {
        const trimmedAttributes = attributes.trim();
        const normalizedAttributes = trimmedAttributes.length > 0 ? ` ${trimmedAttributes}` : '';

        return `<${tagName}${normalizedAttributes}></${tagName}>`;
    });
}

function findSourceTagEnd(template: string, tagStart: number): number {
    let quote: string | null = null;

    for (let index = tagStart + 1; index < template.length; index += 1) {
        const char = template[index];

        if (quote) {
            if (char === quote) {
                quote = null;
            }
            continue;
        }

        if (char === '"' || char === "'") {
            quote = char;
            continue;
        }

        if (char === '>') {
            return index;
        }
    }

    return -1;
}

function collectSwBlockSearchStarts(template: string): number[] {
    const searchStarts: number[] = [];
    let cursor = 0;

    while (cursor < template.length) {
        const start = template.indexOf('<sw-block', cursor);
        if (start === -1) {
            break;
        }

        const nextChar = template[start + '<sw-block'.length];
        if (nextChar && !/[\s>/]/.test(nextChar)) {
            cursor = start + '<sw-block'.length;
            continue;
        }

        const tagEnd = findSourceTagEnd(template, start);
        if (tagEnd === -1) {
            break;
        }

        const sourceTag = template.slice(start, tagEnd + 1);
        if (/\s(?:name|extends)(?:\s*=|\s|\/?>)/.test(sourceTag)) {
            searchStarts.push(tagEnd + 1);
        }
        cursor = tagEnd + 1;
    }

    return searchStarts;
}

/**
 * Creates the stable local key for one condition chain in a block.
 * Use it whenever transformed code and runtime helpers need to refer to the same chain.
 *
 * @example
 * createLegacyConditionChainKey('sw_product_detail_base', 0);
 */
function createLegacyConditionChainKey(blockName: string, chainIndex: number): string {
    return `${blockName}:${chainIndex}`;
}

/**
 * Rewrites native sw-block conditional chains before Vue compiles them.
 * Use it in the template factory pipeline for native `<sw-block>` templates that contain conditional chains.
 *
 * @example
 * transformNativeLegacyBlockConditionals('<sw-block name="base"><div v-if="active"></div></sw-block>');
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
    const sourceSearchStarts = collectSwBlockSearchStarts(template);

    const entries = Array.from(blocks).map((block, blockIndex) => {
        const renderOrderSegment: LegacyConditionRenderOrderSegment = block.hasAttribute('extends')
            ? 'nativeExtension'
            : 'defaultSlot';

        return {
            blockName: block.getAttribute('name') ?? block.getAttribute('extends')!,
            innerTemplate: block.innerHTML,
            renderOrderSegment,
            searchStart: sourceSearchStarts[blockIndex],
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
            searchStart: entry.searchStart,
        });
    });

    fillChainIndices(blockConditionInfos, {}, componentName);

    const allRewrites: RewriteInfo[] = [];
    blockConditionInfos.forEach((blockInfo) => {
        blockInfo.conditionalChains
            .filter((chain) => shouldPerformChainRewrite(chain))
            .forEach((chain) => {
                const rewrites = constructChainAttributeRewrites(chain, blockInfo.renderOrderSegment, componentName);
                allRewrites.push(
                    ...rewrites.map((rewrite) => ({
                        ...rewrite,
                        searchStart: blockInfo.searchStart,
                    })),
                );
            });
    });
    template = applyOrderedRewrites(template, allRewrites);

    return template;
}

/**
 * Finds top-level `v-if` / `v-else-if` / `v-else` chains inside one block.
 * Use it before assigning chain keys so continuation chains across neighboring blocks can be detected.
 *
 * @example
 * const chains = collectBlockConditionalChains('sw_card', parseBlockTemplateChildren(template));
 */
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

/**
 * Parses a block's inner template and returns only its top-level element children.
 * Use it when analyzing condition chains without compiling the whole Vue template.
 *
 * @example
 * const children = parseBlockTemplateChildren('<div v-if="active"></div>');
 */
function parseBlockTemplateChildren(template: string): Element[] {
    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    return Array.from(parsedTemplate.content.children);
}

/**
 * Assigns full chain keys and segment-local case indices to collected chains.
 * Use it after collecting all block chains in render order so cross-block continuations share state.
 *
 * @example
 * fillChainIndices(blockConditionInfos, {}, 'sw-product-detail');
 */
function fillChainIndices(
    blockConditionInfos: BlockConditionInfo[],
    caseStartIndexByChainKey: Record<string, number> = {},
    componentName?: string,
): void {
    let lastChain: BlockConditionChainInfo | null = null;
    let startingChain: BlockConditionChainInfo | null = null;

    const nextCaseIndexByChainKey = new Map<string, number>();

    // Iterate over blocks in order and assign chain keys, ensuring that continuation chains receive the same key as
    // their leading chain.
    blockConditionInfos.forEach((blockInfo) => {
        blockInfo.conditionalChains.forEach((chain) => {
            const localChainKey = createLegacyConditionChainKey(blockInfo.blockName, chain.index);

            if (chain.starting) {
                chain.fullChainKey = localChainKey;
                lastChain = chain;
                startingChain = chain;
            } else if (startingChain && lastChain) {
                chain.fullChainKey = startingChain.fullChainKey;
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

/**
 * Normalizes browser-serialized empty attributes back to their Vue source form.
 * Use it before matching parsed element attributes against the original template text.
 *
 * @example
 * normalizeParsedVueTemplate('<div v-else=""></div>');
 */
function normalizeParsedVueTemplate(template: string): string {
    return template.replace(/(\s)([^\s"'<>\/=]+)\s*=\s*(?:""|'')/g, '$1$2');
}

/**
 * Extracts the exact source code for one condition attribute from parsed outer HTML.
 * Use it to build replacement candidates that still match the original template string.
 *
 * @example
 * getAttributeCode('<div v-else></div>', 'v-else');
 */
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

/**
 * Builds possible source snippets for an attribute after browser parsing has normalized it.
 * Use it so rewrites still match templates that used single quotes, double quotes, or bare `v-else`.
 *
 * @example
 * createAttributeCodeCandidates('v-if', 'active', 'v-if="active"');
 */
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

/**
 * Creates the final `v-if` attribute that delegates condition evaluation to a legacy helper.
 * Use it as the replacement value for every transformed condition case.
 *
 * @example
 * createLegacyHelperAttribute('$swLegacyBlockElse', 'sw_card:0', undefined, parameters);
 */
function createLegacyHelperAttribute(
    helperName: string,
    chainKey: string,
    expression: string | null | undefined,
    parameters: HelperParameters,
): string {
    const helperExpression = createLegacyHelperExpression(helperName, chainKey, expression, parameters);

    return `v-if="${escapeDoubleQuotedAttributeValue(helperExpression)}"`;
}

/**
 * Adds one attribute replacement to a rewrite list.
 * Use it while walking a chain's children so replacements can be applied later in source order.
 *
 * @example
 * addAttributeRewrite(rewrites, child, 'v-if', '$swLegacyBlockIf', 'sw_card:0', 'active', parameters);
 */
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

/**
 * Builds all helper-attribute rewrites for a single condition chain.
 * Use it after `fillChainIndices` has assigned the chain key and segment case offsets.
 *
 * @example
 * const rewrites = constructChainAttributeRewrites(chain, 'shimExtension', 'sw-product-detail');
 */
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

/**
 * Decides whether a block contains chains that need compatibility rewrites.
 * Use it to skip simple standalone `v-if` chains that Vue can evaluate natively.
 *
 * @example
 * if (shouldPerformBlockRewrite(chains)) {
 *     // rewrite the block
 * }
 */
function shouldPerformBlockRewrite(chains: BlockConditionChainInfo[]): boolean {
    if (chains.length === 0) {
        return false;
    }
    if (chains.every((chain) => chain.starting && chain.followedBy === undefined)) {
        return false;
    }
    return true;
}

/**
 * Decides whether a specific chain must be rewritten.
 * Use it for leading, trailing, or continuation chains that can be affected by block stacking.
 *
 * @example
 * const rewritableChains = chains.filter((chain) => shouldPerformChainRewrite(chain));
 */
function shouldPerformChainRewrite(chain: BlockConditionChainInfo): boolean {
    return !chain.starting || chain.followedBy !== undefined || chain.firstChainInBlock || chain.lastChainInBlock;
}

/**
 * Finds the earliest matching source snippet for a rewrite after the current cursor.
 * Use it when applying replacements to avoid rewriting a later duplicate attribute first.
 *
 * @example
 * const match = findRewriteMatch(template, ['v-else'], 0);
 */
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

/**
 * Applies attribute rewrites in source order while preserving unmatched template text.
 * Use it after constructing all replacements for one template or block fragment.
 *
 * @example
 * const rewrittenTemplate = applyOrderedRewrites(template, rewrites);
 */
function applyOrderedRewrites(template: string, rewrites: RewriteInfo[]): string {
    let cursor = 0;
    let rewrittenTemplate = template;

    rewrites.forEach((rewrite) => {
        const match = findRewriteMatch(rewrittenTemplate, rewrite.codeBefore, Math.max(cursor, rewrite.searchStart ?? 0));

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
 * Use it when indexing Twig override templates so shims preserve `v-if` / `v-else` behavior across blocks.
 *
 * @example
 * transformLegacyTwigBlockSequenceConditionals(entries, 'sw-product-detail');
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

    // Step 1: Analyze the blocks child elements to find v-if / v-else-if / v-else chains and collect information about
    // their structure, such as whether they are leading or trailing chains and which chains are continuing each other
    // across blocks.
    entries.forEach((entry) => {
        const children = parseBlockTemplateChildren(entry.innerTemplate);
        const conditionalChains = collectBlockConditionalChains(entry.blockName, children);
        blockConditonalChains.push({
            blockName: entry.blockName,
            renderOrderSegment: 'shimExtension',
            conditionalChains,
        });
    });
    // Step 2: Assign stable chain keys to the collected chains, ensuring that continuation chains across blocks receive
    // the same key as their leading chain.
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
                        ...(chain.starting ? { startsChain: true } : {}),
                    })),
            );
            chains.forEach((chain) => {
                const rewrites = constructChainAttributeRewrites(chain, 'shimExtension', componentName);
                entry.innerTemplate = applyOrderedRewrites(entry.innerTemplate, rewrites);
            });
        }
    });

    return entries;
}

/**
 * Collects the next free case index for every already indexed condition chain.
 * Use it while rebuilding the block index so later overrides append cases without reusing slots.
 *
 * @example
 * const offsets = collectExistingCaseStartIndices();
 */
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

/**
 * Rebuilds the legacy Twig block index when stored entries or continuation aliases changed.
 * Use it lazily before reads so aliases discovered by native rewrites are reflected in shim entries.
 *
 * @example
 * ensureLegacyTwigBlockIndex();
 */
function ensureLegacyTwigBlockIndex(): void {
    if (!legacyTwigBlockIndexDirty && legacyTwigBlockIndexVersion === legacyConditionContinuationContextVersion) {
        return;
    }

    legacyTwigBlockIndex.clear();

    for (let entryIndex = 0; entryIndex < indexedLegacyTwigBlockEntries.length; entryIndex += 1) {
        const { componentName } = indexedLegacyTwigBlockEntries[entryIndex];
        const groupedEntries: LegacyTwigBlockSequenceEntry[] = [];

        while (
            entryIndex < indexedLegacyTwigBlockEntries.length &&
            indexedLegacyTwigBlockEntries[entryIndex].componentName === componentName
        ) {
            groupedEntries.push(...indexedLegacyTwigBlockEntries[entryIndex].entries);
            entryIndex += 1;
        }

        entryIndex -= 1;

        const transformedEntries = transformLegacyTwigBlockSequenceConditionals(
            groupedEntries,
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
    }

    legacyTwigBlockIndexDirty = false;
    legacyTwigBlockIndexVersion = legacyConditionContinuationContextVersion;
}

/**
 * Stores extracted Twig block entries so they can be transformed and indexed lazily.
 * Use it from `twig-block-index.ts` after a component override template has been parsed.
 *
 * @example
 * indexLegacyTwigBlockConditionEntries('sw-product-detail', entries);
 *
 * @private
 */
export function indexLegacyTwigBlockConditionEntries(componentName: string, entries: LegacyTwigBlockSequenceEntry[]): void {
    indexedLegacyTwigBlockEntries.push({ componentName, entries });
    legacyTwigBlockIndexDirty = true;
}

/**
 * Returns indexed legacy Twig override entries for one block name.
 * Use it from `<sw-block name="...">` when creating shim slots for registered Twig overrides.
 *
 * @example
 * const entries = getLegacyTwigBlockEntries('sw_product_detail_base');
 *
 * @private
 */
export function getLegacyTwigBlockEntries(blockName: string): BlockEntry[] {
    ensureLegacyTwigBlockIndex();

    return legacyTwigBlockIndex.get(blockName) ?? [];
}

/**
 * Checks whether one block name has legacy Twig override entries.
 * Use it before creating shim slots so `sw-block` can skip work for untouched blocks.
 *
 * @example
 * if (hasLegacyTwigBlockEntries('sw_product_detail_base')) {
 *     // create shim slots
 * }
 *
 * @private
 */
export function hasLegacyTwigBlockEntries(blockName: string): boolean {
    ensureLegacyTwigBlockIndex();

    return legacyTwigBlockIndex.has(blockName);
}

/**
 * Clears all indexed Twig block condition state.
 * Use it from test teardown or component-factory reset paths that rebuild templates from scratch.
 *
 * @example
 * resetLegacyTwigBlockConditionIndex();
 *
 * @private
 */
export function resetLegacyTwigBlockConditionIndex(): void {
    legacyTwigBlockIndex.clear();
    indexedLegacyTwigBlockEntries.length = 0;
    legacyTwigBlockIndexDirty = false;
    legacyTwigBlockIndexVersion = -1;
    resetLegacyConditionContinuationContexts();
}
