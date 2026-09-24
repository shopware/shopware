/**
 * @sw-package framework
 */

/**
 * Lifts a named-slot `<template>` out of the `<sw-block>` elements the twig blocks became, because
 * `<sw-block>` forwards only its default slot:
 *
 *     <sw-block name="a"><template #footer>X</template></sw-block>
 *  →  <template #footer><sw-block name="a">X</sw-block></template>
 *
 * Every block keeps its name and position around the content, so its override target. That only
 * holds when each block on the path wraps nothing but the path; splitting one would need its name
 * twice, and two blocks of one name render every override twice.
 */

import { ElementTypes, NodeTypes } from '@vue/compiler-dom';
import type { ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import { DYNAMIC_SLOT, isConvertedBlock, namedSlotName, parseTemplate } from './template-ast';

const NON_HOISTABLE = 'named slot inside a twig block cannot be hoisted';

type HoistResult = { template: string; blockers: string[] };

type Site = {
    slot: ElementNode;
    chain: ElementNode[];
    reason: string | null;
};

/** Vue's parser verdict keeps kebab-case, PascalCase and `<component :is>` owners together. */
function ownsNamedSlots(node: ElementNode): boolean {
    return node.tagType === ElementTypes.COMPONENT;
}

/** Elements plus non-whitespace text; comments travel with the block. */
function contentChildren(node: ElementNode): TemplateChildNode[] {
    return node.children.filter(
        (child) => child.type === NodeTypes.ELEMENT || (child.type === NodeTypes.TEXT && child.content.trim().length > 0),
    );
}

/** Quote-aware, so an attribute value cannot end the tag early. */
function openingTagEnd(source: string, start: number): number {
    let quote: '"' | "'" | null = null;

    for (let index = start; index < source.length; index += 1) {
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
            return index;
        }
    }

    return -1;
}

/** Every named-slot template directly inside a converted block, with its block chain. */
function findSites(nodes: TemplateChildNode[], parent: ElementNode | null, ancestors: ElementNode[]): Site[] {
    const sites: Site[] = [];

    for (const child of nodes) {
        if (child.type !== NodeTypes.ELEMENT) {
            continue;
        }

        const element = child;
        const slotName = element.tag === 'template' ? namedSlotName(element) : null;

        if (slotName !== null && parent !== null && isConvertedBlock(parent)) {
            const chain: ElementNode[] = [parent];
            let index = ancestors.length - 1;

            while (index >= 0 && isConvertedBlock(ancestors[index])) {
                chain.unshift(ancestors[index]);
                index -= 1;
            }

            // The first non-block ancestor is the component the slot has to address.
            const owner = index >= 0 ? ancestors[index] : null;
            let inner: TemplateChildNode = element;
            let solePath = true;

            for (let position = chain.length - 1; position >= 0; position -= 1) {
                const children = contentChildren(chain[position]);

                if (children.length !== 1 || children[0] !== inner) {
                    solePath = false;
                    break;
                }

                inner = chain[position];
            }

            const reason =
                slotName === DYNAMIC_SLOT
                    ? `${NON_HOISTABLE} (dynamic slot name)`
                    : owner === null || !ownsNamedSlots(owner)
                      ? `${NON_HOISTABLE} (no component owns the slot)`
                      : !solePath
                        ? `${NON_HOISTABLE} (the block wraps more than the slot)`
                        : null;

            sites.push({ slot: element, chain, reason });
        }

        sites.push(...findSites(element.children, element, parent === null ? [] : [...ancestors, parent]));
    }

    return sites;
}

function hoistBlockSlots(template: string): HoistResult {
    let current = template;
    const blockers: string[] = [];

    // One rewrite per parse, since a rebuilt region invalidates later offsets; deepest site first.
    for (let guard = 0; guard < 200; guard += 1) {
        const ast = parseTemplate(current);

        if (ast === null) {
            return { template: current, blockers };
        }

        const sites = findSites(ast.children, null, []);
        const hoistable = sites.filter((site) => site.reason === null);

        if (hoistable.length === 0) {
            for (const site of sites) {
                if (site.reason !== null && !blockers.includes(site.reason)) {
                    blockers.push(site.reason);
                }
            }

            return { template: current, blockers };
        }

        const site = hoistable.reduce((deepest, candidate) =>
            candidate.slot.loc.start.offset > deepest.slot.loc.start.offset ? candidate : deepest,
        );

        const regionStart = site.chain[0].loc.start.offset;
        const regionEnd = site.chain[0].loc.end.offset;
        const slotOpenEnd = openingTagEnd(current, site.slot.loc.start.offset);
        const authoredOpenTag = current.slice(site.slot.loc.start.offset, slotOpenEnd + 1);
        // A self-closing `<template #x />` is re-opened around the blocks; the end tag's real offset
        // keeps `</template >` intact.
        const slotOpenTag = site.slot.isSelfClosing ? `${authoredOpenTag.slice(0, -2).trimEnd()}>` : authoredOpenTag;
        const slotInner = site.slot.isSelfClosing
            ? ''
            : current.slice(slotOpenEnd + 1, current.lastIndexOf('</', site.slot.loc.end.offset - 1));
        const blockOpenTags = site.chain.map((block) => {
            const openEnd = openingTagEnd(current, block.loc.start.offset);

            return current.slice(block.loc.start.offset, openEnd + 1);
        });

        // Newline-separated so prettier reformats the region instead of keeping it on one line.
        const rebuilt = [
            slotOpenTag,
            ...blockOpenTags,
            slotInner.trim(),
            ...site.chain.map(() => '</sw-block>'),
            '</template>',
        ].join('\n');

        current = current.slice(0, regionStart) + rebuilt + current.slice(regionEnd);
    }

    return { template: current, blockers };
}

export { hoistBlockSlots, NON_HOISTABLE };
