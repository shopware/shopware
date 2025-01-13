/**
 * @package admin
 */
import { reactive, type Slot } from 'vue';

const blockContext: Record<string, Slot[]> = reactive({});

function getBlocks(blockName: string): Slot[] {
    return blockContext[blockName] ?? [];
}

function addBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        blockContext[blockName] = [];
    }
    blockContext[blockName].push(block);
}

function removeBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        return;
    }
    blockContext[blockName] = blockContext[blockName].filter((b) => b !== block);

    if (blockContext[blockName].length === 0) {
        delete blockContext[blockName];
    }
}

/**
 * @private
 */
export default function useBlockContext() {
    return {
        blockContext,
        getBlocks,
        addBlock,
        removeBlock,
    };
}
