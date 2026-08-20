/**
 * @sw-package framework
 * @private
 *
 * Block analysis of legacy base templates for the Native → Twig Extension Bridge.
 *
 * The counterpart of `twig-block-index.ts`: that module indexes the blocks legacy *overrides* declare,
 * this one reports the blocks a legacy *base* template owns - and which of them a `sw-native-block-host`
 * wrapper has to stay away from.
 *
 * TwigJS is used as a parser only. The global TwigJS singleton is configured by `template.factory.js`
 * (output tokens filtered, `{% parent %}` registered) before this module is first used.
 */

import Twig from 'twig';
import reconstructInnerTemplate, { type TwigToken } from './reconstruct-twig-template';
import { normalizeSelfClosingTags } from './transform-legacy-block-conditionals';

const CONDITIONAL_ATTRIBUTES = [
    'v-if',
    'v-else-if',
    'v-else',
] as const;

/**
 * Pairs a rendered element with the block that owns it.
 *
 * `blockName` is `null` for content that sits outside every `{% block %}` of the analyzed template.
 * `isBlockPlaceholder` marks an empty block, which contributes no element of its own but still occupies a
 * position - wrapping it would insert a component tag there.
 *
 * @example
 * const owned: OwnedElement = { element, blockName: 'sw_product_detail_base' };
 */
type OwnedElement = {
    element: Element;
    blockName: string | null;
    isBlockPlaceholder?: boolean;
};

/**
 * Parses a raw Twig template into its token tree.
 *
 * Returns `null` for templates TwigJS cannot parse; the normal template pipeline surfaces the error
 * again later, so the bridge only has to opt out.
 *
 * @example
 * const tokens = parseTwigTokens('sw-product-detail', rawTemplate);
 */
function parseTwigTokens(componentName: string, rawTemplate: string): TwigToken[] | null {
    try {
        return Twig.twig({ data: rawTemplate, rethrow: true }).tokens as TwigToken[];
    } catch (error) {
        console.warn(`[sw-native-block-host] Failed to parse the Twig template of "${componentName}":`, error);

        return null;
    }
}

/**
 * Collects every `{% block %}` name declared anywhere in a token tree.
 *
 * Nested blocks count as owned too: the bridge can wrap them independently of their parent block.
 *
 * @example
 * const names = collectBlockNames(tokens);
 */
function collectBlockNames(tokens: TwigToken[]): string[] {
    return tokens.flatMap((token) => {
        if (token.type !== 'logic' || token.token?.blockName === undefined) {
            return [];
        }

        return [
            token.token.blockName,
            ...collectBlockNames(token.token.output ?? []),
        ];
    });
}

/**
 * Checks whether an element carries any Vue conditional directive.
 *
 * @example
 * isConditional(element);
 */
function isConditional(element: Element): boolean {
    return CONDITIONAL_ATTRIBUTES.some((attribute) => element.hasAttribute(attribute));
}

/**
 * Flattens one element list so `{% block %}` boundaries become transparent.
 *
 * `reconstructInnerTemplate` renders every block as `<sw-block name="...">`, so splicing those children
 * into their parent's list reproduces the element sequence Vue will actually compile — which is what
 * decides whether a `v-if` chain crosses a block boundary.
 *
 * @example
 * const owned = flattenBlockBoundaries(root.children, null);
 */
function flattenBlockBoundaries(elements: HTMLCollection | Element[], blockName: string | null): OwnedElement[] {
    return Array.from(elements).flatMap((element) => {
        const ownBlockName = element.tagName.toLowerCase() === 'sw-block' ? element.getAttribute('name') : null;

        if (ownBlockName !== null) {
            const nested = flattenBlockBoundaries(element.children, ownBlockName);

            // An empty block still holds a position between its siblings, so it stays in the list as a
            // placeholder; dropping it would hide a chain that a wrapper for it would break.
            return nested.length > 0
                ? nested
                : [
                      {
                          element,
                          blockName: ownBlockName,
                          isBlockPlaceholder: true,
                      },
                  ];
        }

        return [
            {
                element,
                blockName,
            },
        ];
    });
}

/**
 * Records every block whose content shares a conditional chain with content it does not own.
 *
 * Wrapping such a block would put a component tag between a `v-if` and its `v-else`, which Vue rejects at
 * template-compile time — so the bridge has to leave those blocks alone.
 *
 * @example
 * collectCrossingChains(owned, unsafeBlockNames);
 */
function collectCrossingChains(owned: OwnedElement[], unsafeBlockNames: Set<string>): void {
    let chainOwners = new Set<string | null>();
    // Empty blocks seen since the last chain member. They only matter once another case joins the chain
    // behind them - an empty block trailing a finished chain is harmless to wrap.
    let pendingPlaceholders: (string | null)[] = [];

    const closeChain = (): void => {
        if (chainOwners.size > 1) {
            chainOwners.forEach((blockName) => {
                if (blockName !== null) {
                    unsafeBlockNames.add(blockName);
                }
            });
        }

        chainOwners = new Set<string | null>();
        pendingPlaceholders = [];
    };

    owned.forEach((entry) => {
        if (entry.isBlockPlaceholder) {
            if (chainOwners.size > 0) {
                pendingPlaceholders.push(entry.blockName);
            }

            return;
        }

        if (!isConditional(entry.element)) {
            closeChain();

            return;
        }

        if (entry.element.hasAttribute('v-if')) {
            closeChain();
        }

        pendingPlaceholders.forEach((blockName) => chainOwners.add(blockName));
        pendingPlaceholders = [];
        chainOwners.add(entry.blockName);
    });

    closeChain();
}

/**
 * Walks the parsed template and reports every block that cannot be wrapped safely.
 *
 * `blockName` is the block owning `parent`'s direct children, which the flattening step then refines per
 * element as nested `{% block %}` markers are resolved.
 *
 * @example
 * collectUnsafeBlockNamesFromElements(root, null, unsafeBlockNames);
 */
function collectUnsafeBlockNamesFromElements(
    parent: Element | DocumentFragment,
    blockName: string | null,
    unsafeBlockNames: Set<string>,
): void {
    const owned = flattenBlockBoundaries(parent.children, blockName);

    collectCrossingChains(owned, unsafeBlockNames);

    owned.forEach((entry) => {
        collectUnsafeBlockNamesFromElements(entry.element, entry.blockName, unsafeBlockNames);
    });
}

/**
 * @private
 *
 * Reports the blocks of one raw Twig template whose content takes part in a conditional chain that
 * crosses the block boundary.
 *
 * Runs on the DOM parser, so it is a no-op outside a browser environment — the bridge then treats every
 * block as unsafe rather than emitting a wrapper it cannot validate.
 *
 * @example
 * const unsafe = collectUnsafeBlockNames(tokens);
 */
export function collectUnsafeBlockNames(tokens: TwigToken[]): Set<string> {
    const unsafeBlockNames = new Set<string>();
    const template = reconstructInnerTemplate(tokens);

    if (typeof document === 'undefined') {
        collectBlockNames(tokens).forEach((blockName) => unsafeBlockNames.add(blockName));

        return unsafeBlockNames;
    }

    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    collectUnsafeBlockNamesFromElements(parsedTemplate.content, null, unsafeBlockNames);

    return unsafeBlockNames;
}

/**
 * Describes the blocks one raw Twig template owns, plus the ones no wrapper may touch.
 *
 * @example
 * const analysis: TwigTemplateBlockAnalysis = { blockNames: ['sw_card'], unsafeBlockNames: new Set() };
 */
type TwigTemplateBlockAnalysis = {
    blockNames: string[];
    unsafeBlockNames: Set<string>;
};

/**
 * @private
 *
 * Parses one raw Twig template into its owned block names plus the subset that cannot be wrapped.
 *
 * @example
 * const analysis = analyzeTwigTemplateBlocks('sw-product-detail', rawTemplate);
 */
export function analyzeTwigTemplateBlocks(componentName: string, rawTemplate: string): TwigTemplateBlockAnalysis {
    const tokens = parseTwigTokens(componentName, rawTemplate);

    if (!tokens) {
        return {
            blockNames: [],
            unsafeBlockNames: new Set<string>(),
        };
    }

    return {
        blockNames: collectBlockNames(tokens),
        unsafeBlockNames: collectUnsafeBlockNames(tokens),
    };
}
