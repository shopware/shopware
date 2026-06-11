/**
 * @sw-package framework
 */
import { reactive, type Slot } from 'vue';

type CaseResult = {
    result: boolean;
    isStartingCondition?: boolean;
};

/**
 * @private
 */
export type LegacyConditionRenderOrderSegment = 'defaultSlot' | 'shimExtension' | 'nativeExtension';

type LegacyConditionCaseList = Array<CaseResult | undefined>;

type LegacyConditionChain = {
    defaultSlotCases: LegacyConditionCaseList;
    shimExtensionCases: LegacyConditionCaseList;
    nativeExtensionCases: LegacyConditionCaseList;
    // Keeps shim chains alive across ticks until their owning shim component unmounts.
    persistent: boolean;
};

/**
 * @private
 */
export type LegacyConditionCaseOptions = {
    segmentCaseIndex: number;
    renderOrderSegment: LegacyConditionRenderOrderSegment;
    isStartingCondition?: boolean;
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

const LEGACY_CONDITION_RENDER_ORDER = [
    'defaultSlot',
    'shimExtension',
    'nativeExtension',
] as const satisfies LegacyConditionRenderOrderSegment[];

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

function trackLegacyConditionChain(chainKey: string): void {
    void legacyConditionRenderVersions[chainKey];
}

function createLegacyConditionChain(): LegacyConditionChain {
    return {
        defaultSlotCases: [],
        shimExtensionCases: [],
        nativeExtensionCases: [],
        persistent: false,
    };
}

function getCaseListForRenderOrderSegment(
    chain: LegacyConditionChain,
    renderOrderSegment: LegacyConditionRenderOrderSegment,
): LegacyConditionCaseList {
    if (renderOrderSegment === 'defaultSlot') {
        return chain.defaultSlotCases;
    }

    if (renderOrderSegment === 'shimExtension') {
        return chain.shimExtensionCases;
    }

    return chain.nativeExtensionCases;
}

function getPreviousCaseResults(
    chain: LegacyConditionChain,
    options: LegacyConditionCaseOptions,
): Array<CaseResult | undefined> {
    const previousCaseResults: Array<CaseResult | undefined> = [];

    for (const renderOrderSegment of LEGACY_CONDITION_RENDER_ORDER) {
        const caseList = getCaseListForRenderOrderSegment(chain, renderOrderSegment);
        const lastSegmentCaseIndex =
            renderOrderSegment === options.renderOrderSegment ? options.segmentCaseIndex : caseList.length;

        for (let segmentCaseIndex = 0; segmentCaseIndex < lastSegmentCaseIndex; segmentCaseIndex += 1) {
            const caseResult = caseList[segmentCaseIndex];

            if (caseResult?.isStartingCondition) {
                previousCaseResults.length = 0;
            }

            previousCaseResults.push(caseResult);
        }

        if (renderOrderSegment === options.renderOrderSegment) {
            return previousCaseResults;
        }
    }

    return previousCaseResults;
}

function createLegacyConditionCaseResult(result: boolean, options: LegacyConditionCaseOptions): CaseResult {
    const caseResult: CaseResult = { result };

    if (options.isStartingCondition === true) {
        caseResult.isStartingCondition = true;
    }

    return caseResult;
}

function scheduleChainUpdate(chainKey: string): void {
    if (pendingUpdates.has(chainKey)) return;

    pendingUpdates.add(chainKey);

    queueMicrotask(() => {
        pendingUpdates.delete(chainKey);
        legacyConditionRenderVersions[chainKey] = (legacyConditionRenderVersions[chainKey] ?? 0) + 1;
    });
}

function setLegacyCaseResult(
    chainKey: string,
    chain: LegacyConditionChain,
    options: LegacyConditionCaseOptions,
    nextResult: CaseResult,
): void {
    const caseList = getCaseListForRenderOrderSegment(chain, options.renderOrderSegment);
    const previous = caseList[options.segmentCaseIndex];

    caseList[options.segmentCaseIndex] = nextResult;

    if (
        chain.persistent &&
        (previous?.result !== nextResult.result || previous?.isStartingCondition !== nextResult.isStartingCondition)
    ) {
        scheduleChainUpdate(chainKey);
    }
}

/** Starts a legacy conditional chain for one block render. */
function legacyIf(chainKey: string, expression: unknown, options: LegacyConditionCaseOptions): boolean {
    const result = Boolean(expression);

    if (!legacyConditionContext[chainKey]) {
        legacyConditionContext[chainKey] = createLegacyConditionChain();
    }

    const chain = legacyConditionContext[chainKey];

    if (options.renderOrderSegment === 'defaultSlot') {
        chain.defaultSlotCases = [];
        chain.nativeExtensionCases = [];
    }

    if (options.renderOrderSegment === 'shimExtension') {
        chain.persistent = true;
    }

    setLegacyCaseResult(chainKey, chain, options, createLegacyConditionCaseResult(result, options));
    scheduleLegacyConditionCleanup(chainKey, chain);

    return result;
}

/** Continues the chain only when no earlier case matched. */
function legacyElseIf(chainKey: string, expression: unknown, options: LegacyConditionCaseOptions): boolean {
    const chain = legacyConditionContext[chainKey];

    if (!chain) {
        return false;
    }

    if (options.renderOrderSegment === 'shimExtension') {
        chain.persistent = true;
    }

    const result = Boolean(expression);
    const previousCaseResults = getPreviousCaseResults(chain, options);
    const previousCaseMatched = previousCaseResults.some((previousCaseResult) => previousCaseResult?.result === true);
    const hasPendingPreviousCase = previousCaseResults.some((previousCaseResult) => previousCaseResult === undefined);

    const caseResult = !hasPendingPreviousCase && !previousCaseMatched && result;
    setLegacyCaseResult(chainKey, chain, options, createLegacyConditionCaseResult(caseResult, options));

    return caseResult;
}

/** Finishes the chain and renders only when all previous cases missed. */
function legacyElse(chainKey: string, options: LegacyConditionCaseOptions): boolean {
    trackLegacyConditionChain(chainKey);
    const chain = legacyConditionContext[chainKey];

    if (!chain) {
        return false;
    }

    if (options.renderOrderSegment === 'shimExtension') {
        chain.persistent = true;
    }

    const previousCaseResults = getPreviousCaseResults(chain, options);
    const previousCaseMatched = previousCaseResults.some((previousCaseResult) => previousCaseResult?.result === true);
    const hasPendingPreviousCase = previousCaseResults.some((previousCaseResult) => previousCaseResult === undefined);

    const result = !hasPendingPreviousCase && !previousCaseMatched;
    setLegacyCaseResult(chainKey, chain, options, createLegacyConditionCaseResult(result, options));

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

    chain.persistent = true;

    const caseList = chain.shimExtensionCases;
    let hasNewReservation = false;

    for (
        let currentIndex = reservation.caseStartIndex;
        currentIndex < reservation.caseStartIndex + reservation.caseCount;
        currentIndex += 1
    ) {
        if (!(currentIndex in caseList)) {
            // Undefined means the case exists, but the shim has not evaluated it yet.
            caseList[currentIndex] = undefined;
            hasNewReservation = true;
        }
    }

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
