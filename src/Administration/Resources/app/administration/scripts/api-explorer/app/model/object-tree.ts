/**
 * @sw-package framework
 */

import type { ApiChange } from '../../diff/types';
import type { ShopwareObjectNode } from '../../snapshot/types';

export interface MergedObjectNode {
    path: string;
    name: string;
    base: ShopwareObjectNode | null;
    head: ShopwareObjectNode | null;
    change: ApiChange | null;
    /** Number of changes on this node and below. */
    changeCount: number;
    /** Changes on this node and below whose severity is not `private`. */
    publicChangeCount: number;
    children: MergedObjectNode[];
}

export interface ObjectTreeRow {
    node: MergedObjectNode;
    depth: number;
}

export interface ObjectTreeFilter {
    changesOnly: boolean;
    showPrivate: boolean;
    query: string;
}

/** Head order; children that only exist in the base follow the sibling that preceded them there. */
function mergeChildren(
    base: ShopwareObjectNode[],
    head: ShopwareObjectNode[],
): Array<[ShopwareObjectNode | null, ShopwareObjectNode | null]> {
    const baseByPath = new Map(
        base.map((node) => [
            node.path,
            node,
        ]),
    );
    const pairs: Array<[ShopwareObjectNode | null, ShopwareObjectNode | null]> = head.map((node) => [
        baseByPath.get(node.path) ?? null,
        node,
    ]);
    const headPaths = new Set(head.map((node) => node.path));

    base.forEach((node, index) => {
        if (headPaths.has(node.path)) {
            return;
        }

        const previous = base
            .slice(0, index)
            .reverse()
            .find((sibling) => pairs.some(([pairBase]) => pairBase === sibling));
        const position = previous ? pairs.findIndex(([pairBase]) => pairBase === previous) + 1 : 0;
        pairs.splice(position, 0, [
            node,
            null,
        ]);
    });

    return pairs;
}

export function mergeObjectTrees(
    base: ShopwareObjectNode | null,
    head: ShopwareObjectNode | null,
    changes: ApiChange[] = [],
): MergedObjectNode | null {
    const changeByPath = new Map<string, ApiChange>();
    changes.filter((change) => change.kind === 'objectMember').forEach((change) => changeByPath.set(change.member, change));

    const merge = (baseNode: ShopwareObjectNode | null, headNode: ShopwareObjectNode | null): MergedObjectNode => {
        const node = (headNode ?? baseNode) as ShopwareObjectNode;
        const children = mergeChildren(baseNode?.children ?? [], headNode?.children ?? []).map(([childBase, childHead]) =>
            merge(childBase, childHead),
        );
        const change = changeByPath.get(node.path) ?? null;

        return {
            path: node.path,
            name: node.name,
            base: baseNode,
            head: headNode,
            change,
            changeCount: (change ? 1 : 0) + children.reduce((sum, child) => sum + child.changeCount, 0),
            publicChangeCount:
                (change && change.severity !== 'private' ? 1 : 0) +
                children.reduce((sum, child) => sum + child.publicChangeCount, 0),
            children,
        };
    };

    return base || head ? merge(base, head) : null;
}

export function isPrivateNode(node: MergedObjectNode): boolean {
    return (node.head ?? node.base)?.visibility === 'private';
}

export function countNodes(node: ShopwareObjectNode | null): number {
    return node ? 1 + node.children.reduce((sum, child) => sum + countNodes(child), 0) : 0;
}

/** Private nodes are only hidden when neither they nor anything below them carries a non-private change. */
export function isHiddenPrivate(node: MergedObjectNode): boolean {
    return isPrivateNode(node) && node.publicChangeCount === 0;
}

/** Topmost nodes that "Show private" off hides, each with its subtree. */
export function countHiddenPrivate(node: MergedObjectNode | null): number {
    if (!node) {
        return 0;
    }

    return isHiddenPrivate(node) ? 1 : node.children.reduce((sum, child) => sum + countHiddenPrivate(child), 0);
}

/**
 * Visible rows in tree order. Hidden private nodes take their subtree along; a search keeps the matches
 * and their ancestors and ignores the collapsed state.
 */
export function visibleObjectRows(
    root: MergedObjectNode | null,
    expanded: Set<string>,
    filter: ObjectTreeFilter,
): ObjectTreeRow[] {
    const query = filter.query.trim().toLowerCase();
    const matches = (node: MergedObjectNode): boolean => {
        if (!filter.showPrivate && isHiddenPrivate(node)) {
            return false;
        }

        const selfMatches =
            (!filter.changesOnly || node.change !== null) && (query === '' || node.path.toLowerCase().includes(query));

        return selfMatches || node.children.some(matches);
    };
    const rows: ObjectTreeRow[] = [];
    const walk = (node: MergedObjectNode, depth: number): void => {
        if (!matches(node)) {
            return;
        }

        rows.push({ node, depth });

        if (query !== '' || expanded.has(node.path)) {
            node.children.forEach((child) => walk(child, depth + 1));
        }
    };

    if (root) {
        walk(root, 0);
    }

    return rows;
}

export function findObjectNode(root: MergedObjectNode | null, path: string): MergedObjectNode | null {
    if (!root) {
        return null;
    }

    if (root.path === path) {
        return root;
    }

    for (const child of root.children) {
        const found = findObjectNode(child, path);

        if (found) {
            return found;
        }
    }

    return null;
}

/** Paths of the node and every ancestor, root first. */
export function ancestorPaths(root: MergedObjectNode | null, path: string): string[] {
    const trail: string[] = [];
    const search = (node: MergedObjectNode): boolean => {
        trail.push(node.path);

        if (node.path === path || node.children.some(search)) {
            return true;
        }

        trail.pop();
        return false;
    };

    return root && search(root) ? trail : [];
}

export function allContainerPaths(root: MergedObjectNode | null): string[] {
    if (!root) {
        return [];
    }

    return root.children.length > 0
        ? [
              root.path,
              ...root.children.flatMap(allContainerPaths),
          ]
        : [];
}
