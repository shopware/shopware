/**
 * @sw-package framework
 */
import { reactive, type Slot } from 'vue';

type LegacyConditionChain = {
    // Stores the result for each absolute condition chain position.
    caseResults: Record<number, boolean | undefined>;
    // Points to the next absolute position for cases rendered in the current pass.
    nextIndex: number;
    // Marks where shim-local condition chain indexes start inside the absolute chain.
    extensionStartIndex?: number;
    // Keeps shim chains alive across ticks until their owning shim component unmounts.
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

/** Returns a stable condition chain index, optionally inside the legacy shim extension range. */
function getLegacyConditionChainIndex(chain: LegacyConditionChain, shimConditionChainIndex?: number): number {
    if (shimConditionChainIndex === undefined) {
        // Native cases render in sequence, so they can consume the next free absolute index.
        const nextIndex = chain.nextIndex;
        chain.nextIndex += 1;

        return nextIndex;
    }

    // Shim cases only know their local index; this offset maps them back into the full chain.
    chain.extensionStartIndex ??= chain.nextIndex;
    chain.persistent = true;

    return chain.extensionStartIndex + shimConditionChainIndex;
}

/** Starts a legacy conditional chain for one block render. */
function legacyIf(blockName: string, expression: unknown): boolean {
    const result = Boolean(expression);

    if (!legacyConditionContext[blockName]) {
        legacyConditionContext[blockName] = {
            caseResults: {},
            nextIndex: 0,
            persistent: false,
        };
    }

    const chain = legacyConditionContext[blockName];
    chain.nextIndex = 0;
    delete chain.extensionStartIndex;
    chain.caseResults[getLegacyConditionChainIndex(chain)] = result;
    scheduleLegacyConditionCleanup(blockName, chain);

    return result;
}

/** Continues the chain only when no earlier case matched. */
function legacyElseIf(blockName: string, expression: unknown, shimConditionChainIndex?: number): boolean {
    const chain = legacyConditionContext[blockName];

    if (!chain) {
        return false;
    }

    const index = getLegacyConditionChainIndex(chain, shimConditionChainIndex);
    const result = Boolean(expression);
    let hasPendingPreviousCase = false;
    let previousCaseMatched = false;

    for (let currentIndex = 0; currentIndex < index; currentIndex += 1) {
        const previousCaseResult = chain.caseResults[currentIndex];

        previousCaseMatched = previousCaseMatched || previousCaseResult === true;
        hasPendingPreviousCase = hasPendingPreviousCase || previousCaseResult === undefined;

        if (previousCaseMatched || hasPendingPreviousCase) {
            break;
        }
    }

    const caseResult = !hasPendingPreviousCase && !previousCaseMatched && result;
    chain.caseResults[index] = caseResult;

    return caseResult;
}

/** Finishes the chain and renders only when all previous cases missed. */
function legacyElse(blockName: string, shimConditionChainIndex?: number): boolean {
    const chain = legacyConditionContext[blockName];

    if (!chain) {
        return false;
    }

    const index = getLegacyConditionChainIndex(chain, shimConditionChainIndex);
    let hasPendingPreviousCase = false;
    let previousCaseMatched = false;

    for (let currentIndex = 0; currentIndex < index; currentIndex += 1) {
        const previousCaseResult = chain.caseResults[currentIndex];

        previousCaseMatched = previousCaseMatched || previousCaseResult === true;
        hasPendingPreviousCase = hasPendingPreviousCase || previousCaseResult === undefined;

        if (previousCaseMatched || hasPendingPreviousCase) {
            break;
        }
    }

    const result = !hasPendingPreviousCase && !previousCaseMatched;
    chain.caseResults[index] = result;

    if (!chain.persistent) {
        delete legacyConditionContext[blockName];
    }

    return result;
}

/** Reserves condition chain slots for shim cases before their render function runs. */
function reserveLegacyConditionCases(blockName: string, shimConditionChainIndex: number, caseCount: number): void {
    const chain = legacyConditionContext[blockName];

    if (!chain || caseCount < 1) {
        return;
    }

    chain.extensionStartIndex ??= chain.nextIndex;
    chain.persistent = true;

    const startIndex = chain.extensionStartIndex + shimConditionChainIndex;

    for (let currentIndex = startIndex; currentIndex < startIndex + caseCount; currentIndex += 1) {
        if (!(currentIndex in chain.caseResults)) {
            // Undefined means the case exists, but the shim has not evaluated it yet.
            chain.caseResults[currentIndex] = undefined;
        }
    }

    chain.nextIndex = Math.max(chain.nextIndex, startIndex + caseCount);
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
        reserveLegacyConditionCases,
        clearLegacyConditionChain,
    };
}
