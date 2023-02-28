import { createLocalVue, shallowMount } from '@vue/test-utils';
import swOrderList from 'src/module/sw-order/page/sw-order-list';
import 'src/app/component/data-grid/sw-data-grid';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-list', swOrderList);

const mockItem = {
    orderNumber: '1',
    orderCustomer: {
        customerId: '2'
    },
    addresses: [
        {
            street: '123 Random street'
        }
    ],
    currency: {
        translated: { shortName: 'EUR' }
    },
    stateMachineState: {
        translated: { name: 'Open' },
        name: 'Open'
    },
    salesChannel: {
        name: 'Test'
    },
    transactions: new EntityCollection(null, null, null, new Criteria(1, 25), [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' }
            },
        }
    ]),
    deliveries: [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' }
            }
        }
    ],
    billingAddress: {
        street: '123 Random street'
    }
};

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.filter('currency', key => key);
    localVue.filter('date', key => key);

    return shallowMount(await Shopware.Component.build('sw-order-list'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
                    <div>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                    </div>
                `
            },
            'sw-button': true,
            'sw-label': true,
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-pagination': true,
            'sw-icon': true,
            'sw-data-grid-settings': true,
            'sw-empty-state': true,
            'router-link': true,
            'sw-checkbox-field': true,
            'sw-data-grid-skeleton': true,
            'sw-time-ago': true,
            'sw-color-badge': true
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            stateStyleDataProviderService: {
                getStyle: () => {
                    return {
                        variant: 'success'
                    };
                }
            },
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve([]) })
            },
            filterFactory: {},
            searchRankingService: {
                getSearchFieldsByEntity: () => {
                    return Promise.resolve({
                        name: searchRankingPoint.HIGH_SEARCH_RANKING
                    });
                },
                buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                    return criteria;
                }
            },
        },
        mocks: {
            $route: { query: '' }
        }
    });
}

Shopware.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria
    };
});

describe('src/module/sw-order/page/sw-order-list', () => {
    let wrapper;
    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        await wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled add button', async () => {
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should have an disabled add button', async () => {
        wrapper = await createWrapper(['order.creator']);
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should contain manual label correctly', async () => {
        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    createdById: '1'
                },
                {
                    ...mockItem
                }
            ]
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const secondRow = wrapper.find('.sw-data-grid__row--1');

        expect(firstRow.find('.sw-order-list__manual-order-label').exists()).toBeTruthy();
        expect(secondRow.find('.sw-order-list__manual-order-label').exists()).toBeFalsy();
    });

    it('should add query score to the criteria', async () => {
        await wrapper.setData({
            term: 'foo'
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not get search ranking fields when term is null', async () => {
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(0);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not build query score when search ranking field is null ', async () => {
        await wrapper.setData({
            term: 'foo'
        });

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show empty state when there is not item after filling search term', async () => {
        await wrapper.setData({
            term: 'foo'
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        const emptyState = wrapper.find('sw-empty-state-stub');

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(emptyState.exists()).toBeTruthy();
        expect(emptyState.attributes().title).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toEqual(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show correct label for payment status', async () => {
        mockItem.transactions = new EntityCollection(null, null, null, new Criteria(1, 25), [
            {
                stateMachineState: {
                    technicalName: 'cancelled',
                    name: 'Cancelled',
                    translated: { name: 'Cancelled' }
                },
            },
            {
                stateMachineState: {
                    technicalName: 'paid',
                    name: 'Paid',
                    translated: { name: 'Paid' }
                },
            },
            {
                stateMachineState: {
                    technicalName: 'open',
                    name: 'Open',
                    translated: { name: 'Open' }
                },
            }
        ]);

        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    createdById: '1'
                },
                {
                    ...mockItem
                }
            ]
        });

        const firstRow = wrapper.findAll('.sw-data-grid__cell .sw-data-grid__cell-content');
        expect(firstRow.at(21).text()).toEqual('Paid');
    });

    it('should push to a new route when editing items', async () => {
        wrapper.vm.$router.push = jest.fn();
        await wrapper.setData({
            $refs: {
                orderGrid: {
                    selection: {
                        foo: { deliveries: [] },
                    },
                },
            },
        });

        await wrapper.vm.onBulkEditItems();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith(expect.objectContaining({
            name: 'sw.bulk.edit.order',
            params: expect.objectContaining({
                excludeDelivery: true
            }),
        }));

        wrapper.vm.$router.push.mockRestore();
    });

    it('should get list with orderCriteria', () => {
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.getLimit()).toEqual(25);
        [
            'addresses',
            'billingAddress',
            'salesChannel',
            'orderCustomer',
            'currency',
            'documents',
            'deliveries',
            'transactions',
        ].forEach(association => expect(criteria.hasAssociation(association)).toBe(true));
    });

    it('should add associations no longer autoload in the orderCriteria', async () => {
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('deliveries').hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('transactions').hasAssociation('stateMachineState')).toBe(true);
    });
});
