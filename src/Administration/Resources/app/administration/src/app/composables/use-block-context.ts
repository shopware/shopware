/**
 * @sw-package framework
 */
import { reactive, type Slot } from 'vue';

type CaseResult = {
    result: boolean;
    isStartingCondition?: boolean;
};

type LegacyConditionChain = {
    caseResults: Record<number, CaseResult | undefined>;
    // Points to the next absolute position for cases rendered in the current pass.
    nextIndex: number;
    // Marks where shim-local condition chain indexes start inside the absolute chain.
    extensionStartIndex?: number;
    // Keeps shim chains alive across ticks until their owning shim component unmounts.
    persistent: boolean;
};

/**
 * @private
 */
export type LegacyConditionCaseOptions = {
    caseIndex: number;
    isStartingCondition?: boolean;
    isShim?: boolean;
};

/**
 * @private
 */
export type LegacyConditionCaseReservation = {
    caseStartIndex: number;
    caseCount: number;
};

const blockContext: Record<string, Slot[]> = reactive({});
const legacyConditionContext: Record<string, LegacyConditionChain> = {};
const legacyConditionRenderVersions = reactive<Record<string, number>>({});
const pendingUpdates = new Set<string>();

// Drops stale chains if no v-else consumes them in the same tick.
function scheduleLegacyConditionCleanup(chainKey: string, chain: LegacyConditionChain): void {
    queueMicrotask(() => {
        if (legacyConditionContext[chainKey] === chain && !chain.persistent) {
            delete legacyConditionContext[chainKey];
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

type LegacyConditionCaseOptionsInput = LegacyConditionCaseOptions | number;

function normalizeLegacyConditionCaseOptions(
    options?: LegacyConditionCaseOptionsInput,
    isStartingCondition?: boolean,
): LegacyConditionCaseOptions | undefined {
    if (typeof options === 'number') {
        return {
            caseIndex: options,
            isStartingCondition,
            isShim: true,
        };
    }

    return options;
}

function trackLegacyConditionChain(chainKey: string): void {
    void legacyConditionRenderVersions[chainKey];
}

/** Returns a stable condition chain index, optionally inside the legacy shim extension range. */
function getLegacyConditionChainIndex(chain: LegacyConditionChain, options?: LegacyConditionCaseOptions): number {
    if (!options) {
        // Native cases render in sequence, so they can consume the next free absolute index.
        const nextIndex = chain.nextIndex;
        chain.nextIndex += 1;

        return nextIndex;
    }

    if (!options.isShim) {
        const nextIndex = Math.max(options.caseIndex, chain.nextIndex);
        chain.nextIndex = nextIndex + 1;

        return nextIndex;
    }

    // Shim cases only know their local index; this offset maps them back into the full chain.
    chain.extensionStartIndex ??= chain.nextIndex;
    chain.persistent = true;

    return chain.extensionStartIndex + options.caseIndex;
}

function getChainStartIndex(chain: Record<number, CaseResult | undefined>, currentIndex: number): number {
    for (let i = currentIndex; i >= 0; i -= 1) {
        if (chain[i]?.isStartingCondition) {
            return i;
        }
    }
    return 0;
}

function createLegacyConditionCaseResult(result: boolean, options?: LegacyConditionCaseOptions): CaseResult {
    const caseResult: CaseResult = { result };

    if (options?.isStartingCondition !== undefined) {
        caseResult.isStartingCondition = options.isStartingCondition;
    }

    return caseResult;
}

function scheduleChainUpdate(chainKey: string): void {
    if (pendingUpdates.has(chainKey)) return;

    pendingUpdates.add(chainKey);

    queueMicrotask(() => {
        pendingUpdates.delete(chainKey);
        legacyConditionRenderVersions[chainKey] =
            (legacyConditionRenderVersions[chainKey] ?? 0) + 1;
    });
}

function setLegacyCaseResult(
    chainKey: string,
    chain: LegacyConditionChain,
    index: number,
    nextResult: CaseResult,
): void {
    const previous = chain.caseResults[index];

    chain.caseResults[index] = nextResult;

    if (
        chain.persistent &&
        (
            previous?.result !== nextResult.result ||
            previous?.isStartingCondition !== nextResult.isStartingCondition
        )
    ) {
        scheduleChainUpdate(chainKey);
    }
}

/** Starts a legacy conditional chain for one block render. */
function legacyIf(
    chainKey: string,
    expression: unknown,
    options?: LegacyConditionCaseOptionsInput,
    isStartingCondition?: boolean,
): boolean {
    const result = Boolean(expression);
    const normalizedOptions = normalizeLegacyConditionCaseOptions(options, isStartingCondition);

    if (!legacyConditionContext[chainKey]) {
        legacyConditionContext[chainKey] = {
            caseResults: {},
            nextIndex: 0,
            persistent: false,
        };
    }

    const chain = legacyConditionContext[chainKey];
    chain.nextIndex = 0;
    delete chain.extensionStartIndex;

    setLegacyCaseResult(
        chainKey,
        chain,
        getLegacyConditionChainIndex(chain, normalizedOptions),
        createLegacyConditionCaseResult(result, normalizedOptions),
    );
    scheduleLegacyConditionCleanup(chainKey, chain);

    return result;
}

/** Continues the chain only when no earlier case matched. */
function legacyElseIf(
    chainKey: string,
    expression: unknown,
    options?: LegacyConditionCaseOptionsInput,
    isStartingCondition?: boolean,
): boolean {
    const chain = legacyConditionContext[chainKey];
    const normalizedOptions = normalizeLegacyConditionCaseOptions(options, isStartingCondition);

    if (!chain) {
        return false;
    }

    const index = getLegacyConditionChainIndex(chain, normalizedOptions);
    const result = Boolean(expression);
    let hasPendingPreviousCase = false;
    let previousCaseMatched = false;

    const chainStartIndex = getChainStartIndex(chain.caseResults, index);
    for (let currentIndex = chainStartIndex; currentIndex < index; currentIndex += 1) {
        const previousCaseResult = chain.caseResults[currentIndex];

        previousCaseMatched = previousCaseMatched || previousCaseResult?.result === true;
        hasPendingPreviousCase = hasPendingPreviousCase || previousCaseResult === undefined;

        if (previousCaseMatched || hasPendingPreviousCase) {
            break;
        }
    }

    const caseResult = !hasPendingPreviousCase && !previousCaseMatched && result;
    setLegacyCaseResult(
        chainKey,
        chain,
        index,
        createLegacyConditionCaseResult(caseResult, normalizedOptions),
    );

    return caseResult;
}

/** Finishes the chain and renders only when all previous cases missed. */
function legacyElse(
    chainKey: string,
    options?: LegacyConditionCaseOptionsInput,
    isStartingCondition?: boolean,
): boolean {
    trackLegacyConditionChain(chainKey);
    const chain = legacyConditionContext[chainKey];
    const normalizedOptions = normalizeLegacyConditionCaseOptions(options, isStartingCondition);

    if (!chain) {
        return false;
    }

    const index = getLegacyConditionChainIndex(chain, normalizedOptions);
    let hasPendingPreviousCase = false;
    let previousCaseMatched = false;

    const chainStartIndex = getChainStartIndex(chain.caseResults, index);
    for (let currentIndex = chainStartIndex; currentIndex < index; currentIndex += 1) {
        const previousCaseResult = chain.caseResults[currentIndex];

        previousCaseMatched = previousCaseMatched || previousCaseResult?.result === true;
        hasPendingPreviousCase = hasPendingPreviousCase || previousCaseResult === undefined;

        if (previousCaseMatched || hasPendingPreviousCase) {
            break;
        }
    }

    const result = !hasPendingPreviousCase && !previousCaseMatched;
    setLegacyCaseResult(
        chainKey,
        chain,
        index,
        createLegacyConditionCaseResult(result, normalizedOptions),
    );

    if (!chain.persistent) {
        delete legacyConditionContext[chainKey];
    }

    return result;
}

/** Reserves condition chain slots for shim cases before their render function runs. */
function reserveLegacyConditionCases(chainKey: string, reservation: LegacyConditionCaseReservation): void {
    const chain = legacyConditionContext[chainKey];

    if (!chain || reservation.caseCount < 1) {
        return;
    }

    chain.extensionStartIndex ??= chain.nextIndex;
    chain.persistent = true;

    const startIndex = chain.extensionStartIndex + reservation.caseStartIndex;
    let hasNewReservation = false;

    for (let currentIndex = startIndex; currentIndex < startIndex + reservation.caseCount; currentIndex += 1) {
        if (!(currentIndex in chain.caseResults)) {
            // Undefined means the case exists, but the shim has not evaluated it yet.
            chain.caseResults[currentIndex] = undefined;
            hasNewReservation = true;
        }
    }

    chain.nextIndex = Math.max(chain.nextIndex, startIndex + reservation.caseCount);

    if (hasNewReservation) {
        scheduleChainUpdate(chainKey);
    }
}

/** Clears persistent shim chain state when the owning shim tree is removed. */
function clearLegacyConditionChain(chainKey: string): void {
    if (!legacyConditionContext[chainKey]) {
        return;
    }

    delete legacyConditionContext[chainKey];
    scheduleChainUpdate(chainKey);
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
