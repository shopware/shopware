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

    afterEach(() => {
        jest.useRealTimers();
    });

    it('should reject when a chunk still fails after the retries', async () => {
        jest.useFakeTimers();
        const error = new Error('timeout of 30000ms exceeded');
        const generator = new VariantsGenerator();
        const sync = jest.fn(() => Promise.reject(error));
        generator.syncService = { sync };

        const result = generator
            .saveVariants({ deleteQueue: [], createQueue: createVariants(150) })
            .catch((reason) => reason);
        await jest.advanceTimersByTimeAsync(15000);

        expect(await result).toBe(error);
        // the first chunk plus three retries, the second chunk is never sent
        expect(sync).toHaveBeenCalledTimes(4);
    });

    it('should reject without retrying when the server rejects a chunk as invalid', async () => {
        jest.useFakeTimers();
        const error = Object.assign(new Error('Request failed with status code 400'), {
            response: { status: 400, data: { errors: [{ code: 'CONTENT__DUPLICATE_PRODUCT_NUMBER' }] } },
        });
        const generator = new VariantsGenerator();
        const sync = jest.fn(() => Promise.reject(error));
        generator.syncService = { sync };

        const onRejected = jest.fn();
        generator.saveVariants({ deleteQueue: [], createQueue: createVariants(150) }).catch(onRejected);
        await jest.advanceTimersByTimeAsync(15000);

        expect(sync).toHaveBeenCalledTimes(1);
        expect(onRejected).toHaveBeenCalledWith(error);
    });

    it('should send chunks of 100 variants and queue their indexing with the indexing-behavior header', async () => {
        const generator = new VariantsGenerator();
        const sync = jest.fn(() => Promise.resolve({ data: {}, deleted: {} }));
        generator.syncService = { sync };

        await generator.saveVariants({ deleteQueue: ['old-variant'], createQueue: createVariants(250) });

        expect(sync.mock.calls.map(([[operation]]) => [operation.action, operation.payload.length])).toEqual([
            ['delete', 1],
            ['upsert', 100],
            ['upsert', 100],
            ['upsert', 50],
        ]);
        sync.mock.calls.forEach(([, params, headers]) => {
            expect(params).toEqual({});
            expect(headers).toEqual({ 'single-operation': 1, 'indexing-behavior': 'use-queue-indexing' });
        });
    });
});
