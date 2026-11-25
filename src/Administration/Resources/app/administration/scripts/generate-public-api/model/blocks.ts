/**
 * @sw-package framework
 *
 * What the model holds about the blocks of a component: the entry of a block, what its place offers to an
 * override, and the elements and `v-if` chains that cross a block boundary.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagStates } from './flag-states';
import type { SourceLine } from './source-line';

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

/**
 * @private
 *
 * How the snapshot and the findings name the block of a crossing. `null` is the template outside of every block.
 */
export function blockLabel(block: string | null): string {
    return block ?? '(root)';
}

/**
 * @private
 *
 * What crosses a block boundary: an element with its tag, or a `v-if` chain.
 */
export type CrossingSubject = { kind: 'element'; tag: string } | { kind: 'chain' };

/**
 * @private
 *
 * An element that opens in one block and closes in another, or a `v-if` chain that continues in another block.
 * A block of `null` is the template outside of every block. `at` is the line of the element, for a chain that
 * of the member that continues it.
 */
export type Crossing = CrossingSubject & {
    opens: string | null;
    closes: string | null;
    states: FlagStates;
    at: SourceLine;
};

/**
 * @private
 *
 * What stands for the subject in the element id of a crossing: the tag of the element, or `v-if` for a chain.
 */
export function crossingSubject(crossing: CrossingSubject): string {
    return crossing.kind === 'chain' ? 'v-if' : crossing.tag;
}

/**
 * @private
 *
 * The part of the element id that names a crossing, `div@sw_card>sw_card_title`. Crossings are sorted by it.
 */
export function crossingId(crossing: Crossing): string {
    return `${crossingSubject(crossing)}@${blockLabel(crossing.opens)}>${blockLabel(crossing.closes)}`;
}
