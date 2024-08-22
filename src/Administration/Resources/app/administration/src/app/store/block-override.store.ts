/**
 * @package customer-order
 * @private
 */
import type { Slot } from 'vue';

/**
 * @private
 */
export type BlockOverrideState = {
    state: {
        blocks: Record<string, Slot[]>;
    },
    actions: {
        getBlocks(blockName: string): Slot[],
        addBlock(blockName: string, block?: Slot): void,
        removeBlock(blockName: string, block?: Slot): void,
    },
    getters: unknown,
};

/**
 * @private
 */
export default Shopware.Store.wrapStoreDefinition({
    id: 'blockOverrideState',

    state: (): BlockOverrideState['state'] => ({
        blocks: {},
    }),

    actions: {
        getBlocks(blockName: string): Slot[] {
            return this.blocks[blockName] ?? [];
        },
        addBlock(blockName: string, block?: Slot): void {
            if (!block) {
                return;
            }
            if (!this.blocks[blockName]) {
                this.blocks[blockName] = [];
            }
            this.blocks[blockName].push(block);
        },
        removeBlock(blockName: string, block?: Slot): void {
            if (!block) {
                return;
            }
            if (!this.blocks[blockName]) {
                return;
            }
            this.blocks[blockName] = this.blocks[blockName].filter((b) => b !== block);
        },
    },
});

