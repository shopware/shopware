/**
 * @sw-package framework
 */

import './block-override.store';

describe('block-override.store', () => {
    let store;

    beforeEach(() => {
        store = Shopware.Store.get('blockOverride');
    });

    it('starts without layers for a block', () => {
        expect(store.getBlockLayers('sw_block_override_store_test')).toStrictEqual([]);
    });

    it('adds and removes a block layer', () => {
        const layer = { render: () => [] };

        store.addBlockLayer('sw_block_override_store_test', layer);

        expect(store.getBlockLayers('sw_block_override_store_test')).toStrictEqual([layer]);

        store.removeBlockLayer('sw_block_override_store_test', layer);

        expect(store.getBlockLayers('sw_block_override_store_test')).toStrictEqual([]);
    });
});
