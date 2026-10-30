import { mount } from '@vue/test-utils';
import type Criteria from 'src/core/data/criteria.data';

/**
 * @sw-package checkout
 */

async function loadOrderCriteria(): Promise<Criteria> {
    const get = jest.fn((id: string, context: unknown, criteria: Criteria) => Promise.resolve({ id, context, criteria }));

    mount(await wrapTestComponent('sw-order-detail', { sync: true }), {
        shallow: true,
        global: {
            mocks: { $route: { name: 'sw.order.detail.general', params: {}, meta: { $module: { routes: {} } } } },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get,
                        createVersion: () => Promise.resolve({ versionId: 'version-id' }),
                        hasChanges: () => false,
                        deleteVersion: () => Promise.resolve(),
                    }),
                },
                orderService: {},
            },
        },
        props: { orderId: 'order-id' },
    });
    await flushPromises();

    return get.mock.calls[0][2];
}

describe('sw-order-detail', () => {
    it('loads the deliveries sorted by shipping costs, most expensive first', async () => {
        const criteria = await loadOrderCriteria();

        expect(criteria.getAssociation('deliveries').sortings).toEqual([
            expect.objectContaining({ field: 'shippingCosts.unitPrice', order: 'DESC' }),
        ]);
    });
});
