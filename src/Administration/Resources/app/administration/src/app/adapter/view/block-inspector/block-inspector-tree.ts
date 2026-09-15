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
 * The block the developer picked in the page, with the blocks enclosing it, innermost first.
 *
 * @private
 */
export type BlockPick = {
    blockName: string;
    enclosingBlockNames: string[];
};

/**
 * @private
 */
export type BlockTreeOptions = {
    /** Case-insensitive filter over block and component names, from the devtools tree filter. */
    filter?: string;
    /** Bumped on every pick so that all node ids change. */
    generation: number;
    pick?: BlockPick | null;
};

const BLOCK_PREFIX = 'block:';
const PICKED_PREFIX = 'picked:';
const COMPONENT_PREFIX = 'component:';
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

function buildPickedNode(pick: BlockPick, blocksByName: Map<string, TreeBlock>, generation: number): CustomInspectorNode {
    return {
        id: pickedNodeId(pick.blockName, generation),
        label: pick.blockName,
        tags: [
            TAG_PICKED,
            ...tagsOf(blocksByName.get(pick.blockName)),
        ],
        children: pick.enclosingBlockNames
            .filter((name) => name !== pick.blockName)
            .map((name) => ({
                id: pickedNodeId(name, generation),
                label: name,
                tags: tagsOf(blocksByName.get(name)),
            })),
    };
}

/**
 * Builds the inspector tree: the picked block first, when there is one, then every block grouped
 * under the component that owns it, in the order the blocks were given.
 *
 * @private
 */
export function buildBlockTree(blocks: TreeBlock[], options: BlockTreeOptions): CustomInspectorNode[] {
    const { generation, pick = null } = options;
    const query = (options.filter ?? '').trim().toLowerCase();
    const blocksByName = new Map(
        blocks.map((block) => [
            block.name,
            block,
        ]),
    );
    const componentNodes = new Map<string, CustomInspectorNode>();

    blocks.forEach((block) => {
        if (!matchesFilter(block, query)) {
            return;
        }

        let componentNode = componentNodes.get(block.component);

        if (!componentNode) {
            componentNode = {
                id: nodeId(generation, COMPONENT_PREFIX, block.component),
                label: block.component,
                children: [],
            };
            componentNodes.set(block.component, componentNode);
        }

        componentNode.children?.push({
            id: blockNodeId(block.name, generation),
            label: block.name,
            tags: tagsOf(block),
        });
    });

    const tree = Array.from(componentNodes.values());

    if (pick) {
        tree.unshift(buildPickedNode(pick, blocksByName, generation));
    }

    return tree;
}
