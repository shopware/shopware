/**
 * @sw-package framework
 */

import { Comment, isVNode, type Slot, type VNode } from 'vue';

/**
 * Calling a slot always yields an array, and Vue's `normalizeVNode` turns any array — even one of
 * length 1 — into a `Fragment`. A component rendering a fragment has no root element: Vue cannot
 * apply fallthrough attributes to it, and `$el` is the fragment's text anchor instead of an
 * element, which breaks every directive and caller that measures or appends to it.
 *
 * Reducing block content that really is single-rooted back to that one VNode keeps a converted
 * component as single-rooted as it was before the block conversion. Content that genuinely has
 * several roots is returned untouched — it was a fragment before the conversion too.
 */
function reduceToSingleRoot<T extends ReturnType<Slot> | VNode | null | undefined>(nodes: T): T | VNode {
    if (!Array.isArray(nodes)) {
        return nodes;
    }

    let single: VNode | undefined;

    for (const node of nodes) {
        if (!isVNode(node)) {
            return nodes;
        }

        if (isAuthorComment(node)) {
            continue;
        }

        if (single) {
            return nodes;
        }

        single = node;
    }

    return single ?? nodes;
}

/**
 * Comments an author wrote, as opposed to the placeholder a falsy `v-if` renders.
 *
 * The distinction has to hold in both builds: the development compiler keeps author comments and
 * emits `createCommentVNode('v-if')` for the placeholder, the production one drops author comments
 * and emits `createCommentVNode('')`. Skipping only non-empty, non-`v-if` comments therefore makes
 * both builds agree on the root shape. Counting the placeholder is what keeps that shape stable:
 * dropping it would turn the component single-rooted while the condition is falsy and multi-rooted
 * once it flips, and Vue answers a changed root type with an unmount plus remount.
 */
function isAuthorComment(node: VNode): boolean {
    return node.type === Comment && typeof node.children === 'string' && node.children !== '' && node.children !== 'v-if';
}

/**
 * @private
 */
export default reduceToSingleRoot;
