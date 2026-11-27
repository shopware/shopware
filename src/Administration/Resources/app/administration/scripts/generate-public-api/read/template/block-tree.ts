/**
 * @sw-package framework
 *
 * The blocks of a template as a tree in the order of the source, the way Twig nests them. The extend chains
 * are resolved on this tree, and `listBlocks` turns it into the entries the snapshot prints.
 */
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { BlockContext, BlockOrigin, TemplateBlock } from '../../model/blocks';
import { FLAG_STATES, inBothStates, mergeStates, perState } from '../../model/flag-states';
import type { FlagStates } from '../../model/flag-states';
import type { SourceLine } from '../../model/source-line';

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

const NO_CONTEXT: BlockContext = { afterIf: null, continuesChain: false, scope: [] };

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
    const visit = (content: BlockContent[], ancestors: readonly BlockNode[]): TemplateBlock[] =>
        content.filter(isBlock).flatMap((block) => [
            ...FLAG_STATES.filter((state) => block.states[state]).map((state) => ({
                name: block.name,
                parent: ancestors.findLast((ancestor) => ancestor.states[state])?.name ?? null,
                origin: block.origin,
                context: contexts.get(block) ?? NO_CONTEXT,
                states: perState((other) => other === state),
                at: block.at,
                markers: block.markers,
            })),
            ...visit(block.content, [...ancestors, block]),
        ]);

    return mergeStates(visit(tree, []), entryKey);
}
