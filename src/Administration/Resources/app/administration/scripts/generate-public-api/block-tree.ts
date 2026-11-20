/**
 * @sw-package framework
 *
 * The blocks of a template as a tree in the order of the source, the way Twig nests them. The extend chains
 * are resolved on this tree, and `listBlocks` turns it into the entries the snapshot prints.
 */
import { byCodePoint } from './code-point-order';
import { FLAG_STATES, inAnyState, inBothStates, perState } from './flag-states';
import type { FlagStates } from './flag-states';

/** @private */
export const BLOCK_ORIGINS = [
    'inherited',
    'replaced',
    'keepsParent',
] as const;

/**
 * @private
 *
 * How an extend child got a block: `inherited` unchanged from its parent, `replaced` by a redeclaration without
 * `{% parent %}`, or redeclared with it (`keepsParent`). `null` for a block the component declares itself.
 */
export type BlockOrigin = (typeof BLOCK_ORIGINS)[number];

/** @private */
export interface Placement {
    /** The flag states in which the place renders, given that the block around it does. */
    within: FlagStates;
    /** The flag states in which the place renders. */
    states: FlagStates;
}

/** @private */
export interface BlockNode extends Placement {
    kind: 'block';
    name: string;
    origin: BlockOrigin | null;
    content: BlockContent[];
}

/**
 * @private
 *
 * A `{% parent %}` tag.
 */
export interface ParentCall extends Placement {
    kind: 'parent';
}

/** @private */
export type BlockContent = BlockNode | ParentCall;

/**
 * @private
 *
 * A block under one parent block. A block that sits under another parent in the major state has two entries,
 * and so has a block that an extend child both inherits and declares under the same parent.
 */
export interface TemplateBlock {
    name: string;
    parent: string | null;
    origin: BlockOrigin | null;
    states: FlagStates;
}

/** @private */
export function isBlock(item: BlockContent): item is BlockNode {
    return item.kind === 'block';
}

/** @private */
export function hasBlock(content: BlockContent[], name: string): boolean {
    return content.filter(isBlock).some((block) => block.name === name || hasBlock(block.content, name));
}

/**
 * @private
 *
 * The content at a place that renders in `states`. Content that moves keeps the conditions between it and the
 * block around it, and loses the ones around the place it came from.
 */
export function placeContent(content: BlockContent[], states: FlagStates): BlockContent[] {
    return content.map((item) => {
        const placed = { ...item, states: inBothStates(states, item.within) };

        return isBlock(placed) ? { ...placed, content: placeContent(placed.content, placed.states) } : placed;
    });
}

/**
 * @private
 *
 * The blocks of the tree, sorted by name, parent and origin. The parent of a block in a flag state is the
 * closest block around it that exists in that state.
 */
export function listBlocks(tree: BlockContent[]): TemplateBlock[] {
    const blocks = new Map<string, TemplateBlock>();
    const visit = (content: BlockContent[], ancestors: readonly BlockNode[]): void => {
        content.filter(isBlock).forEach((block) => {
            FLAG_STATES.filter((state) => block.states[state]).forEach((state) => {
                const parent = ancestors.findLast((ancestor) => ancestor.states[state])?.name ?? null;
                const key = [block.name, parent ?? '', block.origin ?? ''].join('\n');
                const states = perState((other) => other === state);
                const known = blocks.get(key);

                blocks.set(key, {
                    name: block.name,
                    parent,
                    origin: block.origin,
                    states: known ? inAnyState(known.states, states) : states,
                });
            });

            visit(block.content, [...ancestors, block]);
        });
    };

    visit(tree, []);

    return [...blocks.values()].sort(
        (a, b) =>
            byCodePoint(a.name, b.name) ||
            byCodePoint(a.parent ?? '', b.parent ?? '') ||
            byCodePoint(a.origin ?? '', b.origin ?? ''),
    );
}
