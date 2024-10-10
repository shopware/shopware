/**
 * @package customer-order
 * @private
 */
import type { Slot } from 'vue';

/**
 * @private
 */
const blockOverrideStore = Shopware.Store.register({
    id: 'blockOverride',

    state: () => ({
        blocks: {} as Record<string, Slot[]>,
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

/**
 * @private
 */
export default blockOverrideStore;

/**
 * @private
 */
export type BlockOverrideStore = ReturnType<typeof blockOverrideStore>;
