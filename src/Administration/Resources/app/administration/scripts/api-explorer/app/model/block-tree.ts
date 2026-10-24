/**
 * @sw-package framework
 */

import type { ApiChange } from '../../diff/types';
import type { BlockEntry } from '../../snapshot/types';

export interface MergedBlock {
    /** The diff's match key: the block name when it is unique on both sides, otherwise its key. */
    id: string;
    name: string;
    depth: number;
    base: BlockEntry | null;
    head: BlockEntry | null;
    change: ApiChange | null;
    /** Block names from the root down to the block itself; empty on the side where the block is missing. */
    chainBefore: string[];
    chainAfter: string[];
}

function countNames(blocks: BlockEntry[]): Map<string, number> {
    const counts = new Map<string, number>();
    blocks.forEach((block) => counts.set(block.name, (counts.get(block.name) ?? 0) + 1));

    return counts;
}

/** Parent of each block by document order, which avoids guessing from `~n` key suffixes. */
function parentsOf(blocks: BlockEntry[]): Map<BlockEntry, BlockEntry | null> {
    const parents = new Map<BlockEntry, BlockEntry | null>();
    const stack: BlockEntry[] = [];

    blocks.forEach((block) => {
        stack.length = Math.min(stack.length, block.depth);
        parents.set(block, stack[block.depth - 1] ?? null);
        stack[block.depth] = block;
    });

    return parents;
}

function chainOf(block: BlockEntry, parents: Map<BlockEntry, BlockEntry | null>): string[] {
    const chain: string[] = [];

    for (let current: BlockEntry | null = block; current; current = parents.get(current) ?? null) {
        chain.unshift(current.name);
    }

    return chain;
}

interface TreeNode {
    id: string;
    children: string[];
}

/**
 * Merges the block trees of both sides into one tree in head order. Blocks that only exist in the base
 * stay under their base parent, right after the sibling that preceded them in the base.
 */
export function mergeBlockTrees(
    baseBlocks: BlockEntry[],
    headBlocks: BlockEntry[],
    changes: ApiChange[] = [],
): MergedBlock[] {
    const baseCounts = countNames(baseBlocks);
    const headCounts = countNames(headBlocks);
    const idOf = (block: BlockEntry): string =>
        baseCounts.get(block.name) === 1 && headCounts.get(block.name) === 1 ? block.name : block.key;

    const baseParents = parentsOf(baseBlocks);
    const headParents = parentsOf(headBlocks);
    const baseById = new Map<string, BlockEntry>();
    const headById = new Map<string, BlockEntry>();
    baseBlocks.forEach((block) => baseById.set(idOf(block), baseById.get(idOf(block)) ?? block));
    headBlocks.forEach((block) => headById.set(idOf(block), headById.get(idOf(block)) ?? block));

    const ROOT = '';
    const nodes = new Map<string, TreeNode>([[ROOT, { id: ROOT, children: [] }]]);
    const nodeFor = (id: string): TreeNode => {
        let node = nodes.get(id);

        if (!node) {
            node = { id, children: [] };
            nodes.set(id, node);
        }

        return node;
    };

    headById.forEach((block, id) => {
        const parent = headParents.get(block);
        nodeFor(id);
        nodeFor(parent ? idOf(parent) : ROOT).children.push(id);
    });

    const baseChildren = new Map<string, string[]>();
    baseById.forEach((block, id) => {
        const parent = baseParents.get(block);
        const parentId = parent ? idOf(parent) : ROOT;
        baseChildren.set(parentId, [
            ...(baseChildren.get(parentId) ?? []),
            id,
        ]);
    });

    baseChildren.forEach((siblings, parentId) => {
        siblings.forEach((id, index) => {
            if (headById.has(id)) {
                return;
            }

            nodeFor(id);
            const children = nodeFor(parentId).children;
            const previous = siblings
                .slice(0, index)
                .reverse()
                .find((sibling) => children.includes(sibling));
            children.splice(previous === undefined ? 0 : children.indexOf(previous) + 1, 0, id);
        });
    });

    const changeById = new Map<string, ApiChange>();
    changes.filter((change) => change.kind === 'block').forEach((change) => changeById.set(change.member, change));

    const merged: MergedBlock[] = [];
    const visited = new Set<string>();
    const walk = (id: string, depth: number): void => {
        if (visited.has(id)) {
            return;
        }
        visited.add(id);

        const base = baseById.get(id) ?? null;
        const head = headById.get(id) ?? null;
        merged.push({
            id,
            name: (head ?? base)?.name ?? id,
            depth,
            base,
            head,
            change: changeById.get(id) ?? null,
            chainBefore: base ? chainOf(base, baseParents) : [],
            chainAfter: head ? chainOf(head, headParents) : [],
        });
        nodeFor(id).children.forEach((child) => walk(child, depth + 1));
    };
    nodeFor(ROOT).children.forEach((id) => walk(id, 0));

    return merged;
}

/** Changed blocks plus their ancestors, so every changed block stays reachable in the tree. */
export function withChangedOnly(blocks: MergedBlock[]): MergedBlock[] {
    const keep = new Set<MergedBlock>();
    const ancestors: MergedBlock[] = [];

    blocks.forEach((block) => {
        ancestors.length = block.depth;
        if (block.change) {
            ancestors.forEach((ancestor) => keep.add(ancestor));
            keep.add(block);
        }
        ancestors[block.depth] = block;
    });

    return blocks.filter((block) => keep.has(block));
}
