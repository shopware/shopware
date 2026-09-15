/**
 * @sw-package framework
 */

import { cloneVNode, Fragment, isVNode, type VNode, type VNodeArrayChildren } from 'vue';
import { BLOCK_MARKER_ATTRIBUTE, mergeBlockMarker } from 'src/core/factory/block-inspector';

type MarkableNodes = VNode | VNodeArrayChildren | null | undefined;

function markVNode(node: VNode, blockName: string): VNode {
    if (node.type === Fragment) {
        if (!Array.isArray(node.children)) {
            return node;
        }

        const fragment = cloneVNode(node);
        fragment.children = markChildren(node.children, blockName);

        return fragment;
    }

    const isElement = typeof node.type === 'string';
    const isComponent = typeof node.type === 'object' || typeof node.type === 'function';

    if (!isElement && !isComponent) {
        return node;
    }

    const existing = node.props?.[BLOCK_MARKER_ATTRIBUTE] as string | undefined;

    return cloneVNode(node, { [BLOCK_MARKER_ATTRIBUTE]: mergeBlockMarker(existing, blockName) });
}

function markChildren(children: VNodeArrayChildren, blockName: string): VNodeArrayChildren {
    return children.map((child) => (isVNode(child) ? markVNode(child, blockName) : child));
}

/**
 * Marks the root elements a block renders with the block name, the same way the template factory
 * marks Twig blocks. Development-only; the caller checks that the block inspector is enabled.
 *
 * Elements and components take the marker as an attribute, which falls through to a component's
 * root. Fragments are walked into, text and comments stay as they are. A marker that is already
 * present, because the Twig marker put it there, is extended instead of replaced.
 *
 * @private
 */
export default function markBlockVNodes<T extends MarkableNodes>(nodes: T, blockName: string): T | VNode {
    if (Array.isArray(nodes)) {
        return markChildren(nodes, blockName) as T;
    }

    if (isVNode(nodes)) {
        return markVNode(nodes, blockName);
    }

    return nodes;
}
