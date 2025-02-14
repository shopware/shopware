/**
 * @sw-package framework
 */

import './block-override.store';

describe('block-override.store', () => {
    let store;

    beforeEach(() => {
        store = Shopware.Store.get('blockOverride');
    });

    it('has initial state', () => {
        expect(store.blockContext).toStrictEqual({});
    });
});
