/**
 * @sw-package framework
 */

import useListing from 'src/app/composables/use-listing';

describe('test/_setup/setup-shopware', () => {
    describe('Shopware.Composables', () => {
        it('loads a composable when it is first read', () => {
            expect(Shopware.Composables.useListing).toBe(useListing);
        });

        it('lets a spec spy on a composable and restore it', () => {
            const spy = jest.spyOn(Shopware.Composables, 'useListing');

            expect(Shopware.Composables.useListing).toBe(spy);

            spy.mockRestore();

            expect(Shopware.Composables.useListing).toBe(useListing);
        });
    });
});
