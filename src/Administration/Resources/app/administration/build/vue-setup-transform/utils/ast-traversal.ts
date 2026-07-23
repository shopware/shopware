/**
 * @sw-package framework
 */

/**
 * Small shared depth-first traversal primitives over Babel nodes.
 *
 * Several analyzer passes walk the same AST shape - enumerate a node's children, or find the first
 * node that matches. These helpers own the child-enumeration boilerplate (flatten arrays, skip
 * position/comment keys) so each pass only has to express its own visitor.
 */

import type { Node as BabelNode } from '@babel/types';
import { isBabelNodeLike } from './babel-patterns';

/** Object keys that never hold traversable child nodes (source positions and comments). */
const NON_NODE_KEYS = [
    'loc',
    'range',
    'start',
    'end',
    'leadingComments',
    'trailingComments',
    'innerComments',
];

type ChildKeyFilter = (key: string) => boolean;

/** A child Babel node paired with the field name it was found under. */
type ChildBabelEntry = {
    node: BabelNode;
    key: string;
};

/** Type-position fields, skipped by walks that only care about value (runtime) positions. */
function isTypeKey(key: string): boolean {
    return [
        'typeAnnotation',
        'typeParameters',
        'typeArguments',
        'returnType',
    ].includes(key);
}

/**
 * Returns the direct child Babel nodes of a node paired with their field key, flattening arrays.
 *
 * `skipKey` drops extra fields (for example type positions) on top of the always-skipped
 * position/comment keys.
 */
function childBabelEntries(node: BabelNode, skipKey?: ChildKeyFilter): ChildBabelEntry[] {
    const entries: ChildBabelEntry[] = [];

    for (const [
        key,
        value,
    ] of Object.entries(node as unknown as Record<string, unknown>)) {
        if (NON_NODE_KEYS.includes(key) || skipKey?.(key)) {
            continue;
        }

        if (Array.isArray(value)) {
            value.forEach((child) => {
                if (isBabelNodeLike(child)) {
                    entries.push({
                        node: child,
                        key,
                    });
                }
            });
            continue;
        }

        if (isBabelNodeLike(value)) {
            entries.push({
                node: value,
                key,
            });
        }
    }

    return entries;
}

/**
 * Returns the direct child Babel nodes of a node, flattening array-valued fields.
 */
function childBabelNodes(node: BabelNode, skipKey?: ChildKeyFilter): BabelNode[] {
    return childBabelEntries(node, skipKey).map((entry) => entry.node);
}

/**
 * Depth-first pre-order search that returns the first non-null visitor result.
 *
 * The visitor receives each node and its parent; `skipKey` narrows which child fields are descended.
 */
function findInTree<T>(
    node: BabelNode | null | undefined,
    visit: (node: BabelNode, parent: BabelNode | null) => T | null,
    skipKey?: ChildKeyFilter,
    parent: BabelNode | null = null,
): T | null {
    if (!node) {
        return null;
    }

    const hit = visit(node, parent);

    if (hit !== null) {
        return hit;
    }

    for (const child of childBabelNodes(node, skipKey)) {
        const found = findInTree(child, visit, skipKey, node);

        if (found !== null) {
            return found;
        }
    }

    return null;
}

export { type ChildBabelEntry, type ChildKeyFilter, childBabelEntries, childBabelNodes, findInTree, isTypeKey };
