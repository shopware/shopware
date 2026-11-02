import { mount } from '@vue/test-utils';
import type Criteria from 'src/core/data/criteria.data';

/**
 * @sw-package checkout
 */

/** @private */
export type DeliveryState = { technicalName: string; translated: { name: string } };

/** @private */
export type Order = {
    primaryOrderDelivery: { stateMachineState: DeliveryState } | null;
    deliveries: Array<{ id: string; stateMachineState: DeliveryState }>;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- a test double of the app's filter service
Shopware.Service().register('filterService', () => ({
    mergeWithStoredFilters: (storeKey: string, criteria: Criteria) => criteria,
}));

/**
 * A delivery state as the order list loads it.
 *
 * @example
 * createDeliveryState('Shipped'); // → { technicalName: 'shipped', translated: { name: 'Shipped' } }
 *
 * @private
 */
export function createDeliveryState(name: string): DeliveryState {
    return { technicalName: name.toLowerCase(), translated: { name } };
}

/**
 * Shows the orders in the order list and returns the delivery state each row shows, in row order.
 * The data grid is a stub that renders only the delivery state column.
 *
 * @private
 */
export async function showDeliveryStates(orders: Order[]): Promise<string[]> {
    const wrapper = mount(await wrapTestComponent('sw-order-list', { sync: true }), {
        shallow: true,
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content" /></div>' },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
                        <div>
                            <div v-for="item in dataSource" class="row">
                                <slot name="column-primaryOrderDelivery.stateMachineState.name" :item="item" />
                            </div>
                        </div>`,
                },
            },
            mocks: { $route: { query: { page: '1', limit: '25' }, meta: { $module: {} } } },
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve(Object.assign([...orders], { total: orders.length })) }),
                },
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

    return wrapper.findAll('.row').map((row) => row.text());
}
