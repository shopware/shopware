/**
 * @sw-package framework
 */
import { reactive, type Slot } from 'vue';

const blockContext: Record<string, Slot[]> = reactive({});
const legacyConditionContext: Record<string, boolean[]> = {};

function scheduleLegacyConditionCleanup(blockName: string, chain: boolean[]): void {
    queueMicrotask(() => {
        if (legacyConditionContext[blockName] === chain) {
            delete legacyConditionContext[blockName];
        }
    });
}

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

/** Starts a legacy conditional chain for one block render. */
function legacyIf(blockName: string, expression: unknown): boolean {
    const result = Boolean(expression);

    const chain = [result];
    legacyConditionContext[blockName] = chain;
    scheduleLegacyConditionCleanup(blockName, chain);

    return result;
}

/** Continues the chain only when no earlier branch matched. */
function legacyElseIf(blockName: string, expression: unknown): boolean {
    const chain = legacyConditionContext[blockName];

    if (!chain) {
        return false;
    }

    const result = Boolean(expression);
    const previousConditionMatched = chain.some(Boolean);

    chain.push(!previousConditionMatched && result);
    scheduleLegacyConditionCleanup(blockName, chain);

    return !previousConditionMatched && result;
}

/** Finishes the chain and renders only when all previous branches missed. */
function legacyElse(blockName: string): boolean {
    const chain = legacyConditionContext[blockName];

    if (!chain) {
        return false;
    }

    delete legacyConditionContext[blockName];

    return !chain.some(Boolean);
}

/**
 * @private
 */
export default function useBlockContext() {
    return {
        blockContext,
        legacyConditionContext,
        getBlocks,
        addBlock,
        removeBlock,
        legacyIf,
        legacyElseIf,
        legacyElse,
    };
}
