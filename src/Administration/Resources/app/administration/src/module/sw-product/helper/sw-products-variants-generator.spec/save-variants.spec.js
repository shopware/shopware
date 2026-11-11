/**
 * @sw-package inventory
 */

import VariantsGenerator from 'src/module/sw-product/helper/sw-products-variants-generator';

function createVariants(count) {
    return Array.from({ length: count }, (_, index) => ({
        parentId: 'parent-id',
        productNumber: `SW10000.${index + 1}`,
        options: [{ id: `option-${index + 1}` }],
    }));
}

describe('src/module/sw-product/helper/sw-products-variants-generator saveVariants', () => {
    beforeAll(() => {
        Shopware.Service().register('syncService', () => ({ httpClient: {} }));
        Shopware.Service().register('cacheApiService', () => ({ indexProducts() {} }));
    });

    it('should queue the indexing of every chunk with the indexing-behavior header', async () => {
        const generator = new VariantsGenerator();
        const sync = jest.fn(() => Promise.resolve({ data: {}, deleted: {} }));
        generator.syncService = { sync };

        await generator.saveVariants({ deleteQueue: ['old-variant'], createQueue: createVariants(12) });

        expect(sync).toHaveBeenCalledTimes(3);
        sync.mock.calls.forEach(([, params, headers]) => {
            expect(params).toEqual({});
            expect(headers).toEqual({ 'single-operation': 1, 'indexing-behavior': 'use-queue-indexing' });
        });
    });
});
