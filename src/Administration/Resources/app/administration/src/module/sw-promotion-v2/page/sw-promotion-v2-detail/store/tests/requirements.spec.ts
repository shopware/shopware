/**
 * @sw-package checkout
 */
import { createPinia, setActivePinia } from 'pinia';
import { swPromotionDetailStoreId, useSwPromotionDetailStore } from '../index';

// The promotion detail page keeps the promotion it edits in this store, so its views and the discount and condition forms read and change one state.
// Its register.ts registers the store when the Administration module loads, under the id extensions use.
describe('the promotion detail store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    /**
     * @requirement Extensions look the store up by its id, so they see the same state as the page.
     */
    it('is the store registered under its id', () => {
        // ACT
        const store = useSwPromotionDetailStore();

        // ASSERT
        expect(store).toBe(Shopware.Store.get(swPromotionDetailStoreId));
    });

    /**
     * @requirement What one part of the page changes, every other part sees.
     */
    it('shares one state between everyone who uses it', () => {
        // ARRANGE
        const changed = true;

        // ACT
        useSwPromotionDetailStore().isLoading = changed;

        // ASSERT
        expect(useSwPromotionDetailStore().isLoading).toEqual(changed);
    });
});
