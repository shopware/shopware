import { reactive, type Slot } from 'vue';

const blockContext: Record<string, Slot[]> = reactive({});

/**
 * @private
 */
export function getBlocks(blockName: string): Slot[] {
    return blockContext[blockName] ?? [];
}

/**
 * @private
 */
export function addBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        blockContext[blockName] = [];
    }
    blockContext[blockName].push(block);
}

/**
 * @private
 */
export function removeBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        return;
    }
    blockContext[blockName] = blockContext[blockName].filter((b) => b !== block);
}
