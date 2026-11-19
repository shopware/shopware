/**
 * @sw-package framework
 *
 * The blocks of a template as a tree in the order of the source, the way Twig nests them. The extend chains
 * are resolved on this tree, and `listBlocks` turns it into the entries the snapshot prints.
 */
import type { Marker } from '../../eslint-rules/deprecation-rules/utils/api-markers';
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

/**
 * @private
 *
 * A line of a template file, for the findings of the break check. The snapshot files never contain it.
 */
export interface SourceLine {
    file: string;
    line: number;
}

/**
 * @private
 *
 * `at` is the line of the `{% block %}` tag or the `<sw-block>` element. `markers` are the `@deprecated` and
 * `@bc-change` markers in the Twig comments directly before the tag.
 */
export interface BlockNode extends Placement {
    kind: 'block';
    name: string;
    origin: BlockOrigin | null;
    content: BlockContent[];
    at: SourceLine;
    markers: Marker[];
}

/**
 * @private
 *
 * A `{% parent %}` tag.
 */
export interface ParentCall extends Placement {
    kind: 'parent';
}

/**
 * @private
 *
 * The HTML between two Twig tags, starting at the line `at`. The merge along an extend chain carries it along
 * without reading it, so `block-structure.ts` can read the template a component renders as one piece.
 */
export interface Markup {
    kind: 'markup';
    html: string;
    at: SourceLine;
}

/** @private */
export type BlockContent = BlockNode | ParentCall | Markup;

/**
 * @private
 *
 * A template variable that an element around a block introduces. `source` says where its value comes from:
 * the expression a `v-for` loops over, such as `products`, or the slot it is a prop of, such as
 * `sw-data-grid#actions`.
 */
export interface ScopeVariable {
    name: string;
    kind: 'v-for' | 'slot';
    source: string;
}

/**
 * @private
 *
 * What the place of a block offers to an override. `afterIf` holds the conditions of the open `v-if` chain the
 * block follows, in the order of the chain, and is `null` when it follows none. `continuesChain` says that the
 * content of the block starts with the `v-else-if` or `v-else` that continues that chain, so an override of
 * the block has to continue it too.
 */
export interface BlockContext {
    afterIf: string[] | null;
    continuesChain: boolean;
    scope: ScopeVariable[];
}

const NO_CONTEXT: BlockContext = { afterIf: null, continuesChain: false, scope: [] };

/**
 * @private
 *
 * A block under one parent block. A block that sits under another parent in the major state has two entries,
 * and so has a block that an extend child both inherits and declares under the same parent, or one that the
 * template declares in places with a different context. `at` and `markers` are those of the first of its places.
 */
export interface TemplateBlock {
    name: string;
    parent: string | null;
    origin: BlockOrigin | null;
    context: BlockContext;
    states: FlagStates;
    at: SourceLine;
    markers: Marker[];
}

/** @private */
export function isBlock(item: BlockContent): item is BlockNode {
    return item.kind === 'block';
}

/** @private */
export function isMarkup(item: BlockContent): item is Markup {
    return item.kind === 'markup';
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
        if (isMarkup(item)) {
            return item;
        }

        const placed = { ...item, states: inBothStates(states, item.within) };

        return isBlock(placed) ? { ...placed, content: placeContent(placed.content, placed.states) } : placed;
    });
}

/** Entries with the same key are one entry, and the entries are sorted by it. */
function entryKey({ name, parent, origin, context }: TemplateBlock): string {
    return [
        name,
        parent ?? '',
        origin ?? '',
        JSON.stringify(context),
    ].join('\n');
}

/**
 * @private
 *
 * The blocks of the tree, sorted by name, parent, origin and context. The parent of a block in a flag state is
 * the closest block around it that exists in that state. `contexts` comes from `readStructure`, and a block
 * without an entry has no context.
 */
export function listBlocks(
    tree: BlockContent[],
    contexts: ReadonlyMap<BlockNode, BlockContext> = new Map<BlockNode, BlockContext>(),
): TemplateBlock[] {
    const blocks = new Map<string, TemplateBlock>();
    const visit = (content: BlockContent[], ancestors: readonly BlockNode[]): void => {
        content.filter(isBlock).forEach((block) => {
            FLAG_STATES.filter((state) => block.states[state]).forEach((state) => {
                const placed: TemplateBlock = {
                    name: block.name,
                    parent: ancestors.findLast((ancestor) => ancestor.states[state])?.name ?? null,
                    origin: block.origin,
                    context: contexts.get(block) ?? NO_CONTEXT,
                    states: perState((other) => other === state),
                    at: block.at,
                    markers: block.markers,
                };
                const key = entryKey(placed);
                const known = blocks.get(key);

                blocks.set(key, known ? { ...known, states: inAnyState(known.states, placed.states) } : placed);
            });

            visit(block.content, [...ancestors, block]);
        });
    };

    visit(tree, []);

    return [...blocks.values()].sort((a, b) => byCodePoint(entryKey(a), entryKey(b)));
}
