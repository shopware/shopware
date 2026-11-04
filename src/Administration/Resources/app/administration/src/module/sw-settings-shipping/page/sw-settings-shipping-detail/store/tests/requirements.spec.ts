/**
 * @sw-package checkout
 */
import { createPinia, setActivePinia } from 'pinia';
import { swShippingDetailStoreId, useSwShippingDetailStore } from '../index';

// The shipping method detail page keeps the shipping method it edits in this store, so the price matrices read and change one state.
// Its register.ts registers the store when the Administration module loads, under the id extensions use.
describe('the shipping detail store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    /**
     * @requirement Extensions look the store up by its id, so they see the same state as the page.
     */
    it('is the store registered under its id', () => {
        // ACT
        const store = useSwShippingDetailStore();

        // ASSERT
        expect(store).toBe(Shopware.Store.get(swShippingDetailStoreId));
    });

    /**
     * @requirement What one part of the page changes, every other part sees.
     */
    it('shares one state between everyone who uses it', () => {
        // ARRANGE
        const changed = { id: 'shipping-method-id' } as Entity<'shipping_method'>;

        // ACT
        useSwShippingDetailStore().shippingMethod = changed;

        // ASSERT
        expect(useSwShippingDetailStore().shippingMethod).toEqual(changed);
    });
});
