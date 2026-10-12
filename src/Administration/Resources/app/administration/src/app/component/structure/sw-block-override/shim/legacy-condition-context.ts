/**
 * @sw-package framework
 */
import { reactive } from 'vue';

/**
 * Stores the boolean outcome for one case in a legacy block condition chain.
 * Use it when evaluating or replaying a generated `v-if`, `v-else-if`, or `v-else` helper call.
 *
 * @example
 * const caseResult: CaseResult = { result: true, isStartingCondition: true };
 */
type CaseResult = {
    result: boolean;
    isStartingCondition?: boolean;
};

/**
 * Names the phase in which a condition case is rendered.
 *
 * A render-order segment is one layer of the final block stack: `defaultSlot` for core block content,
 * `shimExtension` for legacy Twig override shims, and `nativeExtension` for native `<sw-block extends>`.
 * The condition helpers read these segments in that order so a later `v-else` can see earlier branch results.
 *
 * @example
 * const segment: LegacyConditionRenderOrderSegment = 'shimExtension';
 *
 * @private
 */
export type LegacyConditionRenderOrderSegment = 'defaultSlot' | 'shimExtension' | 'nativeExtension';

/**
 * Represents the ordered case slots for one render-order segment.
 * Use it when a shim reserves cases before its component has evaluated them.
 *
 * @example
 * const cases: LegacyConditionCaseList = [{ result: false }, undefined];
 */
type LegacyConditionCaseList = Array<CaseResult | undefined>;

/**
 * Collects all segment-specific case results for one logical conditional chain.
 * Use it when a chain starts in a default block and continues through shim or native block extensions.
 *
 * @example
 * const chain = createLegacyConditionChain();
 */
type LegacyConditionChain = {
    defaultSlotCases: LegacyConditionCaseList;
    shimExtensionCases: LegacyConditionCaseList;
    nativeExtensionCases: LegacyConditionCaseList;
    // Keeps freshly evaluated shim results available for the next reservation.
    keepShimResultsForNextReservation: boolean;
};

/**
 * Describes where one generated condition helper call belongs inside a chain.
 * Use it as the third argument for generated `$swLegacyBlockIf` and `$swLegacyBlockElseIf` calls.
 *
 * @example
 * const options: LegacyConditionCaseOptions = {
 *     segmentCaseIndex: 0,
 *     renderOrderSegment: 'defaultSlot',
 *     isStartingCondition: true,
 * };
 *
 * @private
 */
export type LegacyConditionCaseOptions = {
    segmentCaseIndex: number;
    renderOrderSegment: LegacyConditionRenderOrderSegment;
    isStartingCondition?: boolean;
};

/**
 * Reserves a range of shim case slots before the shim component evaluates them.
 * Use it when `createShimSlot` mounts a legacy Twig override that has transformed conditionals.
 *
 * @example
 * const reservation: LegacyConditionCaseReservation = { caseStartIndex: 1, caseCount: 2 };
 *
 * @private
 */
export type LegacyConditionCaseReservation = {
    caseStartIndex: number;
    caseCount: number;
    startsChain?: boolean;
};

const legacyConditionContext: Record<string, LegacyConditionChain> = {};
const legacyConditionRenderVersions = reactive<Record<string, number>>({});
const pendingUpdates = new Set<string>();

const LEGACY_CONDITION_RENDER_ORDER = [
    'defaultSlot',
    'shimExtension',
    'nativeExtension',
] as const satisfies LegacyConditionRenderOrderSegment[];

/**
 * Registers a reactive dependency on the render version for a chain.
 * Use it inside `else-if` and `else` helpers so pending shim results can trigger a re-render.
 *
 * @example
 * trackLegacyConditionChain('sw_card:0');
 */
function trackLegacyConditionChain(chainKey: string): void {
    void legacyConditionRenderVersions[chainKey];
}

/**
 * Creates an empty chain state with all render-order segments initialized.
 * Use it when the first generated helper call for a chain is evaluated.
 *
 * @example
 * const chain = createLegacyConditionChain();
 */
function createLegacyConditionChain(): LegacyConditionChain {
    return {
        defaultSlotCases: [],
        shimExtensionCases: [],
        nativeExtensionCases: [],
        keepShimResultsForNextReservation: false,
    };
}

/**
 * Selects the case list that belongs to a render-order segment.
 * Use it whenever a helper reads or writes cases without duplicating segment branching logic.
 *
 * @example
 * const shimCases = getCaseListForRenderOrderSegment(chain, 'shimExtension');
 */
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

/**
 * Returns all case results that precede the current helper call in render order.
 * Use it to decide whether an `else-if` or `else` may render after earlier cases.
 *
 * @example
 * const previousCases = getPreviousCaseResults(chain, { segmentCaseIndex: 1, renderOrderSegment: 'defaultSlot' });
 */
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

/**
 * Wraps a boolean result with metadata that marks the start of a fresh condition chain.
 * Use it before storing the outcome of a generated helper call.
 *
 * @example
 * const result = createLegacyConditionCaseResult(false, { segmentCaseIndex: 0, renderOrderSegment: 'defaultSlot' });
 */
function createLegacyConditionCaseResult(result: boolean, options: LegacyConditionCaseOptions): CaseResult {
    const caseResult: CaseResult = { result };

    if (options.isStartingCondition === true) {
        caseResult.isStartingCondition = true;
    }

    return caseResult;
}

/**
 * Batches the reactive render-version bump for one chain into a microtask.
 * Use it when shim reservations or results change and dependent native cases must be re-evaluated.
 *
 * @example
 * scheduleChainUpdate('sw_card:0');
 */
function scheduleChainUpdate(chainKey: string): void {
    if (pendingUpdates.has(chainKey)) return;

    pendingUpdates.add(chainKey);

    queueMicrotask(() => {
        pendingUpdates.delete(chainKey);
        legacyConditionRenderVersions[chainKey] = (legacyConditionRenderVersions[chainKey] ?? 0) + 1;
    });
}

/**
 * Stores the latest case result and schedules updates when branch outcomes change.
 * Use it from each legacy helper after computing its boolean result.
 *
 * @example
 * setLegacyCaseResult('sw_card:0', chain, options, { result: true });
 */
function setLegacyCaseResult(
    chainKey: string,
    chain: LegacyConditionChain,
    options: LegacyConditionCaseOptions,
    nextResult: CaseResult,
): void {
    const caseList = getCaseListForRenderOrderSegment(chain, options.renderOrderSegment);
    const previous = caseList[options.segmentCaseIndex];

    caseList[options.segmentCaseIndex] = nextResult;

    if (previous?.result !== nextResult.result || previous?.isStartingCondition !== nextResult.isStartingCondition) {
        if (options.renderOrderSegment === 'shimExtension') {
            chain.keepShimResultsForNextReservation = true;
        }
        scheduleChainUpdate(chainKey);
    }
}

/**
 * Starts a legacy conditional chain for one block render.
 * Use it from generated `$swLegacyBlockIf` calls that replace the original `v-if`.
 *
 * @example
 * legacyIf('sw_card:0', isVisible, { segmentCaseIndex: 0, renderOrderSegment: 'defaultSlot' });
 */
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

    setLegacyCaseResult(chainKey, chain, options, createLegacyConditionCaseResult(result, options));

    return result;
}

/**
 * Continues a legacy condition chain only when no earlier case matched.
 * Use it from generated `$swLegacyBlockElseIf` calls that replace the original `v-else-if`.
 *
 * @example
 * legacyElseIf('sw_card:0', hasFallback, { segmentCaseIndex: 1, renderOrderSegment: 'shimExtension' });
 */
function legacyElseIf(chainKey: string, expression: unknown, options: LegacyConditionCaseOptions): boolean {
    trackLegacyConditionChain(chainKey);
    const chain = legacyConditionContext[chainKey];

    if (!chain) {
        return false;
    }

    const result = Boolean(expression);
    const previousCaseResults = getPreviousCaseResults(chain, options);
    const previousCaseMatched = previousCaseResults.some((previousCaseResult) => previousCaseResult?.result === true);
    const hasPendingPreviousCase = previousCaseResults.some((previousCaseResult) => previousCaseResult === undefined);

    const caseResult = !hasPendingPreviousCase && !previousCaseMatched && result;
    setLegacyCaseResult(chainKey, chain, options, createLegacyConditionCaseResult(caseResult, options));

    return caseResult;
}

/**
 * Finishes a legacy condition chain and renders only when all previous cases missed.
 * Use it from generated `$swLegacyBlockElse` calls that replace the original `v-else`.
 *
 * @example
 * legacyElse('sw_card:0', { segmentCaseIndex: 2, renderOrderSegment: 'nativeExtension' });
 */
function legacyElse(chainKey: string, options: LegacyConditionCaseOptions): boolean {
    trackLegacyConditionChain(chainKey);
    const chain = legacyConditionContext[chainKey];

    if (!chain) {
        return false;
    }

    const previousCaseResults = getPreviousCaseResults(chain, options);
    const previousCaseMatched = previousCaseResults.some((previousCaseResult) => previousCaseResult?.result === true);
    const hasPendingPreviousCase = previousCaseResults.some((previousCaseResult) => previousCaseResult === undefined);

    const result = !hasPendingPreviousCase && !previousCaseMatched;
    setLegacyCaseResult(chainKey, chain, options, createLegacyConditionCaseResult(result, options));

    return result;
}

/**
 * Reserves condition chain slots for shim cases before their render function runs.
 * Use it from `createShimSlot` so later native cases wait until the shim cases have evaluated.
 *
 * @example
 * reserveLegacyConditionCases('sw_card:0', { caseStartIndex: 1, caseCount: 2 });
 */
function reserveLegacyConditionCases(chainKey: string, reservation: LegacyConditionCaseReservation): void {
    if (reservation.caseCount < 1) {
        return;
    }

    if (!legacyConditionContext[chainKey]) {
        if (reservation.startsChain !== true) {
            return;
        }

        legacyConditionContext[chainKey] = createLegacyConditionChain();
    }

    const chain = legacyConditionContext[chainKey];

    const caseList = chain.shimExtensionCases;
    let hasNewReservation = false;
    const keepShimResultsForNextReservation = chain.keepShimResultsForNextReservation;

    if (keepShimResultsForNextReservation) {
        queueMicrotask(() => {
            if (legacyConditionContext[chainKey] === chain) {
                chain.keepShimResultsForNextReservation = false;
            }
        });
    }

    for (
        let currentIndex = reservation.caseStartIndex;
        currentIndex < reservation.caseStartIndex + reservation.caseCount;
        currentIndex += 1
    ) {
        if (!(currentIndex in caseList)) {
            // Undefined means the case exists, but the shim has not evaluated it yet.
            caseList[currentIndex] = undefined;
            hasNewReservation = true;
        } else if (!keepShimResultsForNextReservation && caseList[currentIndex] !== undefined) {
            // Clear existing results for re-reserved slots to ensure they are up-to-date with the latest shim evaluation.
            caseList[currentIndex] = undefined;
            hasNewReservation = true;
        }
    }

    if (hasNewReservation) {
        scheduleChainUpdate(chainKey);
    }
}

/**
 * Clears chain state when the owning shim tree is removed.
 * Use it from component `beforeUnmount` hooks to prevent stale condition results.
 *
 * @example
 * clearLegacyConditionChain('sw_card:0');
 */
function clearLegacyConditionChain(chainKey: string): void {
    if (!legacyConditionContext[chainKey]) {
        return;
    }

    delete legacyConditionContext[chainKey];
    scheduleChainUpdate(chainKey);
}

/**
 * Clears all chain state for one owning component and block.
 * Use it from `<sw-block name="...">` unmount to remove native and shim chains owned by that block.
 *
 * @example
 * clearLegacyConditionChainsForBlock('sw_card', 42);
 */
function clearLegacyConditionChainsForBlock(blockName: string, ownerUid?: number): void {
    const prefix = typeof ownerUid === 'number' ? `${ownerUid}:${blockName}:` : `${blockName}:`;

    Object.keys(legacyConditionContext).forEach((chainKey) => {
        if (!chainKey.startsWith(prefix)) {
            return;
        }

        clearLegacyConditionChain(chainKey);
    });
}

/**
 * Exposes the legacy condition runtime used by generated global helpers and shim slots.
 * Use it when wiring Vue global properties or tests that need direct access to the shared context.
 *
 * @example
 * const { legacyIf, reserveLegacyConditionCases } = useLegacyConditionContext();
 *
 * @private
 */
export default function useLegacyConditionContext() {
    return {
        legacyConditionContext,
        legacyIf,
        legacyElseIf,
        legacyElse,
        reserveLegacyConditionCases,
        clearLegacyConditionChain,
        clearLegacyConditionChainsForBlock,
    };
}
