/**
 * @sw-package discovery
 */

import SalesChannelProductAssignmentApiService from './sales-channel-product-assignment.api.service';

describe('src/module/sw-sales-channel/service/sales-channel-product-assignment.api.service', () => {
    function createService(assigned) {
        const httpClient = {
            post: jest.fn(async () => ({
                data: { assigned },
                headers: {},
            })),
        };
        const loginService = {
            getToken: () => 'test-token',
        };

        return { httpClient, service: new SalesChannelProductAssignmentApiService(httpClient, loginService) };
    }

    it('posts every source to the assignment action and resolves with the assigned count', async () => {
        const { httpClient, service } = createService(42);

        const assigned = await service.assign('sales-channel-id', {
            productIds: ['product-id'],
            productStreamIds: ['stream-id'],
            categoryIds: ['category-id'],
        });

        expect(assigned).toBe(42);
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
        const { httpClient, service } = createService(0);

        await service.assign('sales-channel-id', { categoryIds: ['category-id'], visibility: 10 });

        expect(httpClient.post.mock.calls[0][1]).toEqual({
            productIds: [],
            productStreamIds: [],
            categoryIds: ['category-id'],
            visibility: 10,
        });
    });
});
