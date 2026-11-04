/**
 * @sw-package inventory
 */
import { createPinia, setActivePinia } from 'pinia';
import { swProductDetailStoreId, useSwProductDetailStore } from '../index';

// The product detail page keeps the product it edits in this store, so its views, the product bulk edit and extensions read and change one state.
// Its register.ts registers the store when the Administration module loads, under the id extensions use.
describe('the product detail store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    /**
     * @requirement Extensions look the store up by its id, so they see the same state as the page.
     */
    it('is the store registered under its id', () => {
        // ACT
        const store = useSwProductDetailStore();

        // ASSERT
        expect(store).toBe(Shopware.Store.get(swProductDetailStoreId));
    });

    /**
     * @requirement What one part of the page changes, every other part sees.
     */
    it('shares one state between everyone who uses it', () => {
        // ARRANGE
        const changed = { id: 'parent-id' } as Entity<'product'>;

        // ACT
        useSwProductDetailStore().parentProduct = changed;

        // ASSERT
        expect(useSwProductDetailStore().parentProduct).toEqual(changed);
    });
});
