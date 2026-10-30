/**
 * @sw-package discovery
 */

import SalesChannelProductAssignmentApiService from './sales-channel-product-assignment.api.service';

describe('src/module/sw-sales-channel/service/sales-channel-product-assignment.api.service', () => {
    function createService(...responses) {
        const httpClient = {
            post: jest.fn(),
        };
        responses.forEach((data) => {
            httpClient.post.mockResolvedValueOnce({ data, headers: {} });
        });
        const loginService = {
            getToken: () => 'test-token',
        };

        return { httpClient, service: new SalesChannelProductAssignmentApiService(httpClient, loginService) };
    }

    it('posts every source to the assignment action and resolves with the result', async () => {
        const { httpClient, service } = createService({ assigned: 42, complete: true });

        const result = await service.assign('sales-channel-id', {
            productIds: ['product-id'],
            productStreamIds: ['stream-id'],
            categoryIds: ['category-id'],
        });

        expect(result).toEqual({ assigned: 42, complete: true });
        expect(httpClient.post).toHaveBeenCalledTimes(1);
        expect(httpClient.post).toHaveBeenCalledWith(
            '/_action/sales-channel/sales-channel-id/product-assignment',
            {
                productIds: ['product-id'],
                productStreamIds: ['stream-id'],
                categoryIds: ['category-id'],
                visibility: 30,
            },
            {
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                    'Content-Type': 'application/json',
                }),
            },
        );
    });

    it('sends empty lists for sources that were not given', async () => {
        const { httpClient, service } = createService({ assigned: 0, complete: true });

        await service.assign('sales-channel-id', { categoryIds: ['category-id'], visibility: 10 });

        expect(httpClient.post.mock.calls[0][1]).toEqual({
            productIds: [],
            productStreamIds: [],
            categoryIds: ['category-id'],
            visibility: 10,
        });
    });

    it('repeats the same request until the server reports the assignment as complete', async () => {
        const { httpClient, service } = createService(
            { assigned: 5000, complete: false },
            { assigned: 5000, complete: false },
            { assigned: 12, complete: true },
        );

        const result = await service.assign('sales-channel-id', { productStreamIds: ['stream-id'] });

        expect(result).toEqual({ assigned: 10012, complete: true });
        expect(httpClient.post).toHaveBeenCalledTimes(3);
        expect(new Set(httpClient.post.mock.calls.map(([, payload]) => JSON.stringify(payload))).size).toBe(1);
    });

    it('stops after the request cap and reports the assignment as incomplete', async () => {
        const { httpClient, service } = createService();
        httpClient.post.mockResolvedValue({ data: { assigned: 1, complete: false }, headers: {} });

        const result = await service.assign('sales-channel-id', { productStreamIds: ['stream-id'] });

        expect(result).toEqual({ assigned: 200, complete: false });
        expect(httpClient.post).toHaveBeenCalledTimes(200);
    });

    it('rejects with the failing request and does not continue', async () => {
        const { httpClient, service } = createService({ assigned: 5000, complete: false });
        httpClient.post.mockRejectedValueOnce(new Error('Whoops!'));

        await expect(service.assign('sales-channel-id', { productStreamIds: ['stream-id'] })).rejects.toThrow('Whoops!');
        expect(httpClient.post).toHaveBeenCalledTimes(2);
    });
});
