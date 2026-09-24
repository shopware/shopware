/**
 * @sw-package framework
 */

import { Comment, Text, isVNode, type VNode, type VNodeArrayChildren } from 'vue';

/**
 * The placeholder a `v-if` renders when no branch matched: `createCommentVNode('v-if')` in development builds,
 * `createCommentVNode('')` in production builds, which also drop author comments.
 */
function isConditionPlaceholder(node: VNode): boolean {
    return node.type === Comment && (node.children === '' || node.children === 'v-if');
}

function isAuthorComment(node: VNode): boolean {
    return node.type === Comment && !isConditionPlaceholder(node);
}

/**
 * Vue turns every array a render function returns into a fragment, which has no root element for fallthrough
 * attributes or `$el`. Returns the one node of block content that really is single-rooted. Author comments do
 * not count as roots, so development and production builds agree; the `v-if` placeholder does, because a root
 * that changes type makes Vue remount the component.
 *
 * @private
 */
export default function reduceToSingleRoot<T extends VNodeArrayChildren | VNode | null | undefined>(nodes: T): T | VNode {
    if (!Array.isArray(nodes) || !nodes.every(isVNode)) {
        return nodes;
    }

    const roots = nodes.filter((node) => !isAuthorComment(node));

    return roots.length === 1 ? roots[0] : nodes;
}

/**
 * Whether rendered content ends with a `v-if` chain that matched no branch.
 *
 * @private
 */
export function endsWithUnmatchedCondition(nodes: VNodeArrayChildren): boolean {
    const roots = nodes
        .filter(isVNode)
        .filter(
            (node) =>
                !isAuthorComment(node) &&
                !(node.type === Text && typeof node.children === 'string' && node.children.trim() === ''),
        );
    const last = roots[roots.length - 1];

    return !!last && isConditionPlaceholder(last);
}
