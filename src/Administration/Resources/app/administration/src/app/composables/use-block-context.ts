/**
 * @sw-package framework
 */
import { reactive, type Slot } from 'vue';

type LegacyConditionChain = {
    // Branch evaluation state keyed by branch slot index.
    branches: Record<number, boolean | undefined>;
    // Next free slot for native (non-extension) branches.
    nextIndex: number;
    // Start offset for extension branch slots.
    extensionStartIndex?: number;
    // Keeps chain alive across ticks for extension-driven evaluation.
    persistent: boolean;
};

const blockContext: Record<string, Slot[]> = reactive({});
const legacyConditionContext: Record<string, LegacyConditionChain> = reactive({});

// Drops stale chains if no v-else consumes them in the same tick.
function scheduleLegacyConditionCleanup(blockName: string, chain: LegacyConditionChain): void {
    queueMicrotask(() => {
        if (legacyConditionContext[blockName] === chain && !chain.persistent) {
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

/** Returns a stable branch slot, optionally inside the legacy shim extension range. */
function getLegacyConditionBranchIndex(chain: LegacyConditionChain, branchIndex?: number): number {
    if (branchIndex === undefined) {
        const nextIndex = chain.nextIndex;
        chain.nextIndex += 1;

        return nextIndex;
    }

    chain.extensionStartIndex ??= chain.nextIndex;
    chain.persistent = true;

    return chain.extensionStartIndex + branchIndex;
}

/** Starts a legacy conditional chain for one block render. */
function legacyIf(blockName: string, expression: unknown): boolean {
    const result = Boolean(expression);

    if (!legacyConditionContext[blockName]) {
        legacyConditionContext[blockName] = {
            branches: {},
            nextIndex: 0,
            persistent: false,
        };
    }

    const chain = legacyConditionContext[blockName];
    chain.nextIndex = 0;
    delete chain.extensionStartIndex;
    chain.branches[getLegacyConditionBranchIndex(chain)] = result;
    scheduleLegacyConditionCleanup(blockName, chain);

    return result;
}

/** Continues the chain only when no earlier branch matched. */
function legacyElseIf(blockName: string, expression: unknown, branchIndex?: number): boolean {
    const chain = legacyConditionContext[blockName];

    if (!chain) {
        return false;
    }

    const index = getLegacyConditionBranchIndex(chain, branchIndex);
    const result = Boolean(expression);
    let hasPendingPreviousCondition = false;
    let previousConditionMatched = false;

    for (let currentIndex = 0; currentIndex < index; currentIndex += 1) {
        const branch = chain.branches[currentIndex];

        previousConditionMatched = previousConditionMatched || branch === true;
        hasPendingPreviousCondition = hasPendingPreviousCondition || branch === undefined;

        if (previousConditionMatched || hasPendingPreviousCondition) {
            break;
        }
    }

    const branchResult = !hasPendingPreviousCondition && !previousConditionMatched && result;
    chain.branches[index] = branchResult;

    return branchResult;
}

/** Finishes the chain and renders only when all previous branches missed. */
function legacyElse(blockName: string, branchIndex?: number): boolean {
    const chain = legacyConditionContext[blockName];

    if (!chain) {
        return false;
    }

    const index = getLegacyConditionBranchIndex(chain, branchIndex);
    let hasPendingPreviousCondition = false;
    let previousConditionMatched = false;

    for (let currentIndex = 0; currentIndex < index; currentIndex += 1) {
        const branch = chain.branches[currentIndex];

        previousConditionMatched = previousConditionMatched || branch === true;
        hasPendingPreviousCondition = hasPendingPreviousCondition || branch === undefined;

        if (previousConditionMatched || hasPendingPreviousCondition) {
            break;
        }
    }

    const result = !hasPendingPreviousCondition && !previousConditionMatched;
    chain.branches[index] = result;

    if (!chain.persistent) {
        delete legacyConditionContext[blockName];
    }

    return result;
}

/** Reserves branch slots for shim components before their render function runs. */
function reserveLegacyConditionBranches(blockName: string, branchIndex: number, branchCount: number): void {
    const chain = legacyConditionContext[blockName];

    if (!chain || branchCount < 1) {
        return;
    }

    chain.extensionStartIndex ??= chain.nextIndex;
    chain.persistent = true;

    const startIndex = chain.extensionStartIndex + branchIndex;

    for (let currentIndex = startIndex; currentIndex < startIndex + branchCount; currentIndex += 1) {
        if (!(currentIndex in chain.branches)) {
            chain.branches[currentIndex] = undefined;
        }
    }

    chain.nextIndex = Math.max(chain.nextIndex, startIndex + branchCount);
}

/** Clears persistent shim chain state when the owning shim tree is removed. */
function clearLegacyConditionChain(blockName: string): void {
    delete legacyConditionContext[blockName];
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
        reserveLegacyConditionBranches,
        clearLegacyConditionChain,
    };
}
