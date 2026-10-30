import { mount } from '@vue/test-utils';
import type Criteria from 'src/core/data/criteria.data';

/**
 * @sw-package checkout
 */

/** @private */
export type DeliveryState = { name: string };

/** @private */
export type Order = {
    primaryOrderDelivery: { stateMachineState: DeliveryState } | null;
    deliveries: Array<{ id: string; shippingCosts?: { unitPrice: number }; stateMachineState: DeliveryState }>;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('filterService', () => ({
    mergeWithStoredFilters: (storeKey: string, criteria: Criteria) => criteria,
}));

/**
 * Mounts the order list and returns its `getDeliveryState()` method,
 * which the delivery status column renders and extensions can override.
 *
 * @private
 */
export async function createDeliveryStateGetter(): Promise<(order: Order) => DeliveryState | null> {
    const wrapper = mount(await wrapTestComponent('sw-order-list', { sync: true }), {
        shallow: true,
        global: {
            mocks: { $route: { query: { page: '1', limit: '25' }, meta: { $module: {} } } },
            provide: {
                repositoryFactory: { create: () => ({ search: () => Promise.resolve([]) }) },
                filterFactory: {},
                stateStyleDataProviderService: { getStyle: () => ({}) },
                searchRankingService: {
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: (fields: unknown, term: string, criteria: Criteria) => criteria,
                    isValidTerm: () => false,
                },
            },
        },
    });
    await flushPromises();

    const vm = wrapper.vm as unknown as { getDeliveryState: (order: Order) => DeliveryState | null };

    return (order) => vm.getDeliveryState(order);
}
