/**
 * @sw-package framework
 */
import { reactive, type Slot } from 'vue';

const blockContext: Record<string, Slot[]> = reactive({});

/**
 * Overrides register a stable wrapper in `blockContext`, so the list itself does not change when an
 * override is handed a new slot function by Vue. This counter is the reactive link the rendering block
 * is missing in that case: every `getBlocks` call reads it, so bumping it re-renders every block that
 * renders the override.
 */
const blockRevisions: Record<string, number> = reactive({});

function getBlocks(blockName: string): Slot[] {
    // Read so a call to `invalidateBlock` re-runs every effect that renders this block.
    void blockRevisions[blockName];

    return blockContext[blockName] ?? [];
}

function invalidateBlock(blockName: string): void {
    blockRevisions[blockName] = (blockRevisions[blockName] ?? 0) + 1;
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
        invalidateBlock,
    };
}
