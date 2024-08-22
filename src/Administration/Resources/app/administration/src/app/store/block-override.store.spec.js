import blockOverrideStore from './block-override.store';

describe('block-override.store', () => {
    let store;
    beforeAll(() => {
        Shopware.Store.register(blockOverrideStore);
    });

    beforeEach(() => {
        store = Shopware.Store.get('blockOverrideState');
    });

    it('has initial state', () => {
        expect(store.blocks).toStrictEqual({});
    });
});
