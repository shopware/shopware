/**
 * @sw-package unknown
 */

describe('use-block-context', () => {
    let useBlockContext;
    beforeEach(async () => {
        useBlockContext = (await import('./use-block-context')).default;
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
});
