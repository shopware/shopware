/**
 * @sw-package framework
 */
import composables from './index';
import useListing from './use-listing';

describe('src/app/composables/attach', () => {
    it('assigns the published composables to Shopware.Composables', async () => {
        expect(Shopware.Composables).toBeUndefined();

        await import('./attach');

        expect(Shopware.Composables).toBe(composables);
        expect(Shopware.Composables.useListing).toBe(useListing);
    });
});
