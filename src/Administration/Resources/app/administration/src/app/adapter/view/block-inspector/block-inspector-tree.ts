/**
 * @sw-package framework
 * @private
 *
 * Tree and node ids of the block inspector, kept free of devtools and DOM so that they can be tested.
 *
 * Jumping to a picked block has to work with both Vue devtools generations:
 *
 * - Devtools v6 honour `selectInspectorNode` and scroll the selected node into view.
 * - Devtools v7 ignore `selectInspectorNode`. Their tree does select the first root node on its
 *   own, but only when the node selected so far has vanished from the refreshed tree.
 *
 * So a pick puts the picked block first, as a root node with the enclosing blocks as children, and
 * bumps a generation that is part of every node id. The old selection no longer exists, v7 falls
 * back to the first root, and v6 is told the new id explicitly.
 */

import type { CustomInspectorNode, InspectorNodeTag } from '@vue/devtools-api';
import type { InspectedBlockKind } from 'src/core/factory/block-inspector';
import type { ContainedBlock } from './block-inspector-dom';

/**
 * @private
 */
export const TAG_TWIG: InspectorNodeTag = { label: 'twig', textColor: 0xffffff, backgroundColor: 0x189eff };

/**
 * @private
 */
export const TAG_NATIVE: InspectorNodeTag = { label: 'native', textColor: 0xffffff, backgroundColor: 0x37d046 };

/**
 * @private
 */
export const TAG_EXTENDED: InspectorNodeTag = { label: 'extended', textColor: 0xffffff, backgroundColor: 0xde294c };

/**
 * @private
 */
export const TAG_PICKED: InspectorNodeTag = { label: 'picked', textColor: 0xffffff, backgroundColor: 0x52667a };

/**
 * What the tree needs to know about one block in the DOM.
 *
 * @private
 */
export type TreeBlock = {
    name: string;
    component: string;
    kind: InspectedBlockKind;
    extended: boolean;
};

/**
 * The block the developer picked in the page, with the blocks nested inside it.
 *
 * @private
 */
export type BlockPick = {
    blockName: string;
    contained: ContainedBlock[];
};

/**
 * @private
 */
export type BlockTreeOptions = {
    /**
     * Case-insensitive filter over block and component names, from the devtools tree filter. A block
     * is kept when it matches or when one of the blocks inside it does, so a match stays reachable.
     */
    filter?: string;
    /** Bumped on every pick so that all node ids change. */
    generation: number;
    pick?: BlockPick | null;
};

const BLOCK_PREFIX = 'block:';
const PICKED_PREFIX = 'picked:';
const GENERATION_SEPARATOR = '|';

function nodeId(generation: number, prefix: string, name: string): string {
    return `${generation}${GENERATION_SEPARATOR}${prefix}${name}`;
}

/**
 * Id of a block node under its component.
 *
 * @private
 */
export function blockNodeId(blockName: string, generation: number): string {
    return nodeId(generation, BLOCK_PREFIX, blockName);
}

/**
 * Id of the picked block root node, and of the enclosing blocks listed below it.
 *
 * @private
 */
export function pickedNodeId(blockName: string, generation: number): string {
    return nodeId(generation, PICKED_PREFIX, blockName);
}

/**
 * The block a node id stands for, or null for component groups and other nodes.
 *
 * @private
 */
export function blockNameFromNodeId(id: string): string | null {
    const separatorIndex = id.indexOf(GENERATION_SEPARATOR);
    const withoutGeneration = separatorIndex === -1 ? id : id.slice(separatorIndex + 1);

    if (withoutGeneration.startsWith(BLOCK_PREFIX)) {
        return withoutGeneration.slice(BLOCK_PREFIX.length);
    }

    if (withoutGeneration.startsWith(PICKED_PREFIX)) {
        return withoutGeneration.slice(PICKED_PREFIX.length);
    }

    return null;
}

function tagsOf(block: TreeBlock | undefined): InspectorNodeTag[] {
    if (!block) {
        return [];
    }

    const tags = [block.kind === 'twig' ? TAG_TWIG : TAG_NATIVE];

    if (block.extended) {
        tags.push(TAG_EXTENDED);
    }

    return tags;
}

function matchesFilter(block: TreeBlock, query: string): boolean {
    return !query || block.name.toLowerCase().includes(query) || block.component.toLowerCase().includes(query);
}

function buildContainedNode(
    block: ContainedBlock,
    blocksByName: Map<string, TreeBlock>,
    generation: number,
): CustomInspectorNode {
    return {
        id: pickedNodeId(block.name, generation),
        label: block.name,
        tags: tagsOf(blocksByName.get(block.name)),
        children: block.children.map((child) => buildContainedNode(child, blocksByName, generation)),
    };
}

/**
 * The picked block as it really sits in the page: the block itself with the blocks nested inside it.
 * The blocks around it are not part of this node - the state panel lists them instead.
 *
 * While a pick is active this is the whole tree. The devtools select and expand the first root node
 * on their own and offer a plugin no way to expand anything else, so focusing the tree on the picked
 * block is what puts it in view - and it keeps the block from appearing twice.
 */
function buildPickedNode(pick: BlockPick, blocksByName: Map<string, TreeBlock>, generation: number): CustomInspectorNode {
    return {
        id: pickedNodeId(pick.blockName, generation),
        label: pick.blockName,
        tags: [
            TAG_PICKED,
            ...tagsOf(blocksByName.get(pick.blockName)),
        ],
        children: pick.contained.map((child) => buildContainedNode(child, blocksByName, generation)),
    };
}

/** One hierarchy node, or null when neither it nor anything inside it matches the filter. */
function buildHierarchyNode(
    block: ContainedBlock,
    blocksByName: Map<string, TreeBlock>,
    generation: number,
    query: string,
): CustomInspectorNode | null {
    const children = block.children
        .map((child) => buildHierarchyNode(child, blocksByName, generation, query))
        .filter((child): child is CustomInspectorNode => child !== null);

    const metadata = blocksByName.get(block.name);
    const matches = !query || (metadata ? matchesFilter(metadata, query) : block.name.toLowerCase().includes(query));

    // A parent that does not match itself is kept as the path to a match below it.
    if (!matches && children.length === 0) {
        return null;
    }

    return {
        id: blockNodeId(block.name, generation),
        label: block.name,
        tags: tagsOf(metadata),
        children,
    };
}

/**
 * Builds the inspector tree: the blocks of the page nested the way they nest in the DOM, the way an
 * element inspector shows a document. While a block is picked the tree is focused on that block,
 * like focusing on a subtree in an element inspector; clearing the pick brings the page back.
 *
 * @private
 */
export function buildBlockTree(
    hierarchy: ContainedBlock[],
    blocks: TreeBlock[],
    options: BlockTreeOptions,
): CustomInspectorNode[] {
    const { generation, pick = null } = options;
    const query = (options.filter ?? '').trim().toLowerCase();
    const blocksByName = new Map(
        blocks.map((block) => [
            block.name,
            block,
        ]),
    );

    if (pick) {
        return [buildPickedNode(pick, blocksByName, generation)];
    }

    return hierarchy
        .map((block) => buildHierarchyNode(block, blocksByName, generation, query))
        .filter((node): node is CustomInspectorNode => node !== null);
}
