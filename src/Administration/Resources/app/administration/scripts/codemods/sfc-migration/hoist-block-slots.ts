/**
 * @sw-package framework
 */

/**
 * Lifts a named-slot `<template>` out of the `{% block %}` wrappers the twig conversion turned into
 * `<sw-block>` elements, so the slot addresses the component that owns it again.
 *
 * A `{% block %}` is transparent, `<sw-block>` is not: it forwards only its default slot, so a named
 * slot below one is re-parented onto the block and its content dropped — markup Vue compiles without
 * complaint. The build transform rejects the shape and names the remedy, "move the <sw-block> inside
 * the named-slot template", which is exactly the inversion performed here:
 *
 *     <sw-block name="a"><template #footer>X</template></sw-block>
 *  →  <template #footer><sw-block name="a">X</sw-block></template>
 *
 * Block nesting order is preserved, so every block keeps its name and its position around the content
 * and therefore its override target. Only shapes where the inversion preserves that are rewritten:
 * every block on the path must wrap nothing but the path, or splitting it would need the same block
 * name twice — and two blocks of one name render every override for it twice.
 */

import { ElementTypes, NodeTypes } from '@vue/compiler-dom';
import type { ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import { isConvertedBlock, parseTemplate } from './template-ast';

const NON_HOISTABLE = 'named slot inside a twig block cannot be hoisted';

type HoistResult = { template: string; blockers: string[] };

type Site = {
    slot: ElementNode;
    chain: ElementNode[];
    reason: string | null;
};

/** The non-default slot a `<template>` addresses, or null when it addresses none. */
function namedSlotOf(node: ElementNode): string | null {
    if (node.tag !== 'template') {
        return null;
    }

    for (const prop of node.props) {
        if (prop.type !== NodeTypes.DIRECTIVE || prop.name !== 'slot') {
            continue;
        }

        if (!prop.arg) {
            return null;
        }

        if (prop.arg.type === NodeTypes.SIMPLE_EXPRESSION && prop.arg.isStatic) {
            return prop.arg.content === 'default' ? null : prop.arg.content;
        }

        // A dynamic argument cannot be proven to be the default slot, so it counts as named.
        return '[dynamic]';
    }

    return null;
}

/**
 * Can this tag own a named slot? Vue's parser already answered that while parsing, and reusing its
 * verdict keeps kebab-case, PascalCase and `<component :is>` owners together — a hyphen test refuses
 * everything but the first, and the build transform accepts all three.
 */
function ownsNamedSlots(node: ElementNode): boolean {
    return node.tagType === ElementTypes.COMPONENT;
}

/** Children carrying content: elements plus non-whitespace text. Comments travel with the block. */
function contentChildren(node: ElementNode): TemplateChildNode[] {
    return node.children.filter(
        (child) => child.type === NodeTypes.ELEMENT || (child.type === NodeTypes.TEXT && child.content.trim().length > 0),
    );
}

/** Offset of an opening tag's `>`, quote-aware so an attribute value cannot end the tag early. */
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

/**
 * Every named-slot template whose direct parent is a converted block, each with the chain of blocks it
 * has to be hoisted through and the reason it cannot be, if any.
 */
function findSites(nodes: TemplateChildNode[], parent: ElementNode | null, ancestors: ElementNode[]): Site[] {
    const sites: Site[] = [];

    for (const child of nodes) {
        if (child.type !== NodeTypes.ELEMENT) {
            continue;
        }

        const element = child;
        const slotName = namedSlotOf(element);

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
                slotName === '[dynamic]'
                    ? `${NON_HOISTABLE} (dynamic slot name)`
                    : owner === null || !ownsNamedSlots(owner)
                      ? `${NON_HOISTABLE} (no component owns the slot)`
                      : !solePath
                        ? `${NON_HOISTABLE} (the block wraps more than the slot)`
                        : null;

            sites.push({ slot: element, chain, reason });
        }

        sites.push(
            ...findSites(
                element.children,
                element,
                parent === null
                    ? []
                    : [
                          ...ancestors,
                          parent,
                      ],
            ),
        );
    }

    return sites;
}

/**
 * Returns the template with every hoistable named slot lifted above its blocks, plus one blocker per
 * distinct shape that could not be hoisted.
 */
function hoistBlockSlots(template: string): HoistResult {
    let current = template;
    const blockers: string[] = [];

    // One rewrite per parse: a rebuilt region invalidates every offset after it. The deepest site is
    // taken first, so an inner rewrite is already in place when the region containing it is rebuilt.
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
        // `<template #x />` carries its content in neither children nor an end tag, so the hoisted
        // form has to re-open it around the blocks; taking the end tag's real offset rather than
        // subtracting a literal `</template>` keeps `</template >` and friends intact too.
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
