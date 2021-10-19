import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/page/sw-order-list';
import 'src/app/component/data-grid/sw-data-grid';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';

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
    transactions: new EntityCollection(null, null, null, new Criteria(), [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' }
            }
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

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.filter('currency', key => key);
    localVue.filter('date', key => key);

    return shallowMount(Shopware.Component.build('sw-order-list'), {
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
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-pagination': true,
            'sw-icon': true,
            'sw-data-grid-settings': true,
            'sw-empty-state': true,
            'router-link': true,
            'sw-checkbox-field': true,
            'sw-data-grid-skeleton': true,
            'sw-time-ago': true
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
            }
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
    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled add button', async () => {
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should have an disabled add button', async () => {
        wrapper = createWrapper(['order.creator']);
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

    it('should add query score to the criteria ', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria();
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});
