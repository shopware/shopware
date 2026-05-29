/**
 * @sw-package framework
 */

describe('use-block-context', () => {
    let useBlockContext;
    let legacyIf;
    let legacyElseIf;
    let legacyElse;
    let reserveLegacyConditionBranches;
    let clearLegacyConditionChain;
    let legacyConditionContext;

    beforeEach(async () => {
        useBlockContext = (await import('./use-block-context')).default;

        const blockContext = useBlockContext();
        legacyIf = blockContext.legacyIf;
        legacyElseIf = blockContext.legacyElseIf;
        legacyElse = blockContext.legacyElse;
        reserveLegacyConditionBranches = blockContext.reserveLegacyConditionBranches;
        clearLegacyConditionChain = blockContext.clearLegacyConditionChain;
        legacyConditionContext = blockContext.legacyConditionContext;
    });

    afterEach(() => {
        jest.resetModules();
    });

    it('has initial empty context', () => {
        const { blockContext } = useBlockContext();

        expect(blockContext).toStrictEqual({});
    });

    it('adds a new block to the context', () => {
        const { addBlock, blockContext } = useBlockContext();
        const testSlot = () => 'test';

        addBlock('test', testSlot);

        expect(blockContext).toStrictEqual({
            test: [testSlot],
        });
    });

    it('adds multiple blocks with the same id', () => {
        const { addBlock, blockContext } = useBlockContext();
        const testSlot1 = () => 'test1';
        const testSlot2 = () => 'test2';
        const testSlot3 = () => 'test3';

        addBlock('test', testSlot1);
        addBlock('test', testSlot2);
        addBlock('test', testSlot3);

        expect(blockContext).toStrictEqual({
            test: [
                testSlot1,
                testSlot2,
                testSlot3,
            ],
        });
    });

    it('adds multiple blocks with different ids', () => {
        const { addBlock, blockContext } = useBlockContext();
        const testSlot1 = () => 'test1';
        const testSlot2 = () => 'test2';
        const testSlot3 = () => 'test3';

        addBlock('test1', testSlot1);
        addBlock('test2', testSlot2);
        addBlock('test3', testSlot3);

        expect(blockContext).toStrictEqual({
            test1: [testSlot1],
            test2: [testSlot2],
            test3: [testSlot3],
        });
    });

    it('returns the block by id', () => {
        const { addBlock, getBlocks } = useBlockContext();
        const testSlot1 = () => 'test1';
        const testSlot2 = () => 'test2';
        const testSlot3 = () => 'test3';

        addBlock('test1', testSlot1);
        addBlock('test2', testSlot2);
        addBlock('test3', testSlot3);

        expect(getBlocks('test1')).toStrictEqual([testSlot1]);
        expect(getBlocks('test2')).toStrictEqual([testSlot2]);
        expect(getBlocks('test3')).toStrictEqual([testSlot3]);
    });

    it('removes blocks by id', () => {
        const { addBlock, removeBlock, blockContext } = useBlockContext();
        const testSlot1 = () => 'test1';
        const testSlot2 = () => 'test2';
        const testSlot3 = () => 'test3';

        addBlock('test1', testSlot1);
        addBlock('test2', testSlot2);
        addBlock('test3', testSlot3);

        removeBlock('test2', testSlot2);

        expect(blockContext).toStrictEqual({
            test1: [testSlot1],
            test3: [testSlot3],
        });
    });

    it('removes a exact block when there are multiple with the same id', () => {
        const { addBlock, removeBlock, blockContext } = useBlockContext();
        const testSlot1 = () => 'test1';
        const testSlot2 = () => 'test2';
        const testSlot3 = () => 'test3';

        addBlock('test', testSlot1);
        addBlock('test', testSlot2);
        addBlock('test', testSlot3);

        removeBlock('test', testSlot2);

        expect(blockContext).toStrictEqual({
            test: [
                testSlot1,
                testSlot3,
            ],
        });
    });

    it('evaluates legacy if / else-if / else chains', () => {
        expect(legacyIf('test', false)).toBe(false);
        expect(legacyElseIf('test', true)).toBe(true);
        expect(legacyElse('test')).toBe(false);
    });

    it('renders legacy else when no previous condition matched', () => {
        expect(legacyIf('test', false)).toBe(false);
        expect(legacyElseIf('test', false)).toBe(false);
        expect(legacyElse('test')).toBe(true);
    });

    it('does not render orphaned legacy else branches', () => {
        expect(legacyElseIf('test', true)).toBe(false);
        expect(legacyElse('test')).toBe(false);
    });

    it('cleans up legacy if chains without an else branch', async () => {
        expect(legacyIf('test', false)).toBe(false);
        expect(legacyElseIf('test', true)).toBe(true);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                branches: {
                    0: false,
                    1: true,
                },
                nextIndex: 2,
                persistent: false,
            },
        });

        await Promise.resolve();

        expect(legacyConditionContext).toStrictEqual({});
    });

    it('keeps legacy else branches available during the current render tick', () => {
        expect(legacyIf('test', false)).toBe(false);
        expect(legacyElse('test')).toBe(true);
        expect(legacyConditionContext).toStrictEqual({});
    });

    it('keeps reserved extension branches pending until the shim renders', async () => {
        expect(legacyIf('test', false)).toBe(false);

        reserveLegacyConditionBranches('test', 0, 1);

        expect(legacyElse('test')).toBe(false);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                branches: {
                    0: false,
                    1: undefined,
                    2: false,
                },
                extensionStartIndex: 1,
                nextIndex: 3,
                persistent: true,
            },
        });

        await Promise.resolve();

        expect(legacyConditionContext).toStrictEqual({
            test: {
                branches: {
                    0: false,
                    1: undefined,
                    2: false,
                },
                extensionStartIndex: 1,
                nextIndex: 3,
                persistent: true,
            },
        });
    });

    it('updates reserved extension branches by their stable branch index', () => {
        expect(legacyIf('test', false)).toBe(false);

        reserveLegacyConditionBranches('test', 0, 2);

        expect(legacyElseIf('test', false, 0)).toBe(false);
        expect(legacyElse('test', 1)).toBe(true);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                branches: {
                    0: false,
                    1: false,
                    2: true,
                },
                extensionStartIndex: 1,
                nextIndex: 3,
                persistent: true,
            },
        });
    });

    it('clears persisted legacy extension chains when the extension is removed', () => {
        expect(legacyIf('test', false)).toBe(false);

        reserveLegacyConditionBranches('test', 0, 1);
        clearLegacyConditionChain('test');

        expect(legacyConditionContext).toStrictEqual({});
    });

    it('keeps persisted extension state when the parent legacy chain renders again', () => {
        expect(legacyIf('test', false)).toBe(false);

        reserveLegacyConditionBranches('test', 0, 1);
        expect(legacyElseIf('test', true, 0)).toBe(true);

        expect(legacyIf('test', false)).toBe(false);
        reserveLegacyConditionBranches('test', 0, 1);
        expect(legacyConditionContext).toStrictEqual({
            test: {
                branches: {
                    0: false,
                    1: true,
                },
                extensionStartIndex: 1,
                nextIndex: 2,
                persistent: true,
            },
        });
    });
});
