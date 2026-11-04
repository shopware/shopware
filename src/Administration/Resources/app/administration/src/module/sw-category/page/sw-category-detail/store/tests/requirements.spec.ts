/**
 * @sw-package inventory
 */
import { createPinia, setActivePinia } from 'pinia';
import { swCategoryDetailStoreId, useSwCategoryDetailStore } from '../index';

// The category and landing page detail pages keep what they edit in this store, so their views and the trees read and change one state.
// Its register.ts registers the store when the Administration module loads, under the id extensions use.
describe('the category detail store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    /**
     * @requirement Extensions look the store up by its id, so they see the same state as the page.
     */
    it('is the store registered under its id', () => {
        // ACT
        const store = useSwCategoryDetailStore();

        // ASSERT
        expect(store).toBe(Shopware.Store.get(swCategoryDetailStoreId));
    });

    /**
     * @requirement What one part of the page changes, every other part sees.
     */
    it('shares one state between everyone who uses it', () => {
        // ARRANGE
        const changed = true;

        // ACT
        useSwCategoryDetailStore().isCategoryColumn = changed;

        // ASSERT
        expect(useSwCategoryDetailStore().isCategoryColumn).toEqual(changed);
    });
});
