/**
 * @sw-package framework
 */

describe('app/component/structure/sw-block-override/shim/legacy-condition-context.ts', () => {
    let legacyIf;
    let legacyElseIf;
    let legacyElse;
    let reserveLegacyConditionCases;
    let clearLegacyConditionChain;
    let clearLegacyConditionChainsForBlock;
    let legacyConditionContext;
    const caseResult = (result) => ({ result });
    const defaultCase = (segmentCaseIndex, isStartingCondition = false) => ({
        segmentCaseIndex,
        renderOrderSegment: 'defaultSlot',
        isStartingCondition,
    });
    const shimCase = (segmentCaseIndex, isStartingCondition = false) => ({
        segmentCaseIndex,
        renderOrderSegment: 'shimExtension',
        isStartingCondition,
    });
    const nativeCase = (segmentCaseIndex, isStartingCondition = false) => ({
        segmentCaseIndex,
        renderOrderSegment: 'nativeExtension',
        isStartingCondition,
    });

    beforeEach(async () => {
        const useLegacyConditionContext = (await import('./legacy-condition-context')).default;

        const legacyConditionBlockContext = useLegacyConditionContext();
        legacyIf = legacyConditionBlockContext.legacyIf;
        legacyElseIf = legacyConditionBlockContext.legacyElseIf;
        legacyElse = legacyConditionBlockContext.legacyElse;
        reserveLegacyConditionCases = legacyConditionBlockContext.reserveLegacyConditionCases;
        clearLegacyConditionChain = legacyConditionBlockContext.clearLegacyConditionChain;
        clearLegacyConditionChainsForBlock = legacyConditionBlockContext.clearLegacyConditionChainsForBlock;
        legacyConditionContext = legacyConditionBlockContext.legacyConditionContext;
    });

    afterEach(() => {
        jest.resetModules();
    });

    it('evaluates legacy if / else-if / else chains', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        expect(legacyElseIf('test', true, defaultCase(1))).toBe(true);
        expect(legacyElse('test', defaultCase(2))).toBe(false);
    });

    it('renders legacy else when no previous condition matched', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        expect(legacyElseIf('test', false, defaultCase(1))).toBe(false);
        expect(legacyElse('test', defaultCase(2))).toBe(true);
    });

    it('does not render orphaned legacy else cases', () => {
        expect(legacyElseIf('test', true, defaultCase(0))).toBe(false);
        expect(legacyElse('test', defaultCase(1))).toBe(false);
    });

    it('keeps legacy chains until lifecycle cleanup', async () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        expect(legacyElseIf('test', true, defaultCase(1))).toBe(true);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                    caseResult(true),
                ],
                shimExtensionCases: [],
                nativeExtensionCases: [],
                keepShimResultsForNextReservation: false,
            },
        });

        await Promise.resolve();

        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                    caseResult(true),
                ],
                shimExtensionCases: [],
                nativeExtensionCases: [],
                keepShimResultsForNextReservation: false,
            },
        });
    });

    it('keeps legacy else cases available during the current render tick', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        expect(legacyElse('test', defaultCase(1))).toBe(true);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                    caseResult(true),
                ],
                shimExtensionCases: [],
                nativeExtensionCases: [],
                keepShimResultsForNextReservation: false,
            },
        });
    });

    it('keeps reserved extension cases pending until the shim renders', async () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);

        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });

        expect(legacyElse('test', nativeCase(0))).toBe(false);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                ],
                shimExtensionCases: [
                    undefined,
                ],
                nativeExtensionCases: [
                    caseResult(false),
                ],
                keepShimResultsForNextReservation: false,
            },
        });

        await Promise.resolve();

        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                ],
                shimExtensionCases: [
                    undefined,
                ],
                nativeExtensionCases: [
                    caseResult(false),
                ],
                keepShimResultsForNextReservation: false,
            },
        });
    });

    it('creates pending extension cases for a Twig-started shim chain', () => {
        reserveLegacyConditionCases('test', {
            caseStartIndex: 0,
            caseCount: 1,
            startsChain: true,
        });

        expect(legacyElse('test', shimCase(1))).toBe(false);
        expect(legacyIf('test', false, shimCase(0, true))).toBe(false);
        expect(legacyElse('test', shimCase(1))).toBe(true);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [],
                shimExtensionCases: [
                    {
                        result: false,
                        isStartingCondition: true,
                    },
                    caseResult(true),
                ],
                nativeExtensionCases: [],
                keepShimResultsForNextReservation: true,
            },
        });
    });

    it('updates parent else cases when reserved extension cases resolve', async () => {
        const { nextTick, watchEffect } = await import('vue');
        const parentElseResults = [];
        const stopEffects = [];

        try {
            stopEffects.push(
                watchEffect(() => {
                    legacyIf('test', false, defaultCase(0, true));
                    reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });

                    parentElseResults.push(legacyElse('test', nativeCase(0)));
                }),
            );

            stopEffects.push(
                watchEffect(() => {
                    legacyElseIf('test', false, shimCase(0));
                }),
            );

            expect(parentElseResults[0]).toBe(false);

            await Promise.resolve();
            await nextTick();

            expect(parentElseResults.at(-1)).toBe(true);
        } finally {
            stopEffects.forEach((stopEffect) => {
                stopEffect();
            });
        }
    });

    it('updates shim else-if cases when previous default slot cases change', async () => {
        const { nextTick, ref, watchEffect } = await import('vue');
        const defaultCondition = ref(false);
        const shimElseIfResults = [];
        const stopEffects = [];

        try {
            stopEffects.push(
                watchEffect(() => {
                    legacyIf('test', defaultCondition.value, defaultCase(0, true));
                    reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });
                }),
            );

            stopEffects.push(
                watchEffect(() => {
                    shimElseIfResults.push(legacyElseIf('test', true, shimCase(0)));
                }),
            );

            expect(shimElseIfResults.at(-1)).toBe(true);

            defaultCondition.value = true;

            await Promise.resolve();
            await nextTick();

            expect(shimElseIfResults.at(-1)).toBe(false);
        } finally {
            stopEffects.forEach((stopEffect) => {
                stopEffect();
            });
        }
    });

    it('updates reserved extension cases by their stable shim condition chain index', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);

        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 2 });

        expect(legacyElseIf('test', false, shimCase(0))).toBe(false);
        expect(legacyElse('test', shimCase(1))).toBe(true);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                ],
                shimExtensionCases: [
                    caseResult(false),
                    caseResult(true),
                ],
                nativeExtensionCases: [],
                keepShimResultsForNextReservation: true,
            },
        });
    });

    it('evaluates shim cases after default slot cases and before native extension cases', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        expect(legacyElseIf('test', false, defaultCase(1))).toBe(false);

        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 2 });

        expect(legacyElseIf('test', true, shimCase(0))).toBe(true);
        expect(legacyElse('test', shimCase(1))).toBe(false);
        expect(legacyElse('test', nativeCase(0))).toBe(false);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                    caseResult(false),
                ],
                shimExtensionCases: [
                    caseResult(true),
                    caseResult(false),
                ],
                nativeExtensionCases: [
                    caseResult(false),
                ],
                keepShimResultsForNextReservation: true,
            },
        });
    });

    it('evaluates native extension cases behind reserved shim cases', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);

        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });

        expect(legacyElse('test', nativeCase(0))).toBe(false);
        expect(legacyElseIf('test', true, shimCase(0))).toBe(true);
        expect(legacyElse('test', nativeCase(0))).toBe(false);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                ],
                shimExtensionCases: [
                    caseResult(true),
                ],
                nativeExtensionCases: [
                    caseResult(false),
                ],
                keepShimResultsForNextReservation: true,
            },
        });
    });

    it('clears legacy extension chains when the extension is removed', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);

        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });
        clearLegacyConditionChain('test');

        expect(legacyConditionContext).toStrictEqual({});
    });

    it('clears legacy chains by owning block', () => {
        expect(legacyIf('42:test:0', false, defaultCase(0, true))).toBe(false);
        expect(legacyIf('42:other:0', false, defaultCase(0, true))).toBe(false);

        clearLegacyConditionChainsForBlock('test', 42);

        expect(legacyConditionContext['42:test:0']).toBeUndefined();
        expect(legacyConditionContext['42:other:0']).toBeDefined();
    });

    it('keeps extension state when the parent legacy chain renders again', () => {
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);

        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });
        expect(legacyElseIf('test', true, shimCase(0))).toBe(true);

        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });
        expect(legacyConditionContext).toStrictEqual({
            test: {
                defaultSlotCases: [
                    { result: false, isStartingCondition: true },
                ],
                shimExtensionCases: [
                    caseResult(true),
                ],
                nativeExtensionCases: [],
                keepShimResultsForNextReservation: true,
            },
        });
    });

    it('keeps native extension cases pending until stale shim cases re-evaluate', async () => {
        // Initial parent render: the reserved shim case is pending, so the native fallback must wait.
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);

        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });
        expect(legacyElse('test', nativeCase(0))).toBe(false);
        expect(legacyElseIf('test', false, shimCase(0))).toBe(false);

        // Follow-up render after the shim resolved to false: the native fallback can now render.
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });
        expect(legacyElse('test', nativeCase(0))).toBe(true);

        await Promise.resolve();

        // Later parent render: the old false result is stale, so the native fallback must wait again.
        expect(legacyIf('test', false, defaultCase(0, true))).toBe(false);
        reserveLegacyConditionCases('test', { caseStartIndex: 0, caseCount: 1 });

        expect(legacyElse('test', nativeCase(0))).toBe(false);
        expect(legacyElseIf('test', true, shimCase(0))).toBe(true);
    });
});
