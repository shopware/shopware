import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';

/**
 * @package customer-order
 */

const mockItem = {
    orderNumber: '1',
    orderCustomer: {
        customerId: '2',
    },
    addresses: [
        {
            street: '123 Random street',
        },
    ],
    currency: {
        isoCode: 'EUR',
    },
    stateMachineState: {
        translated: { name: 'Open' },
        name: 'Open',
    },
    salesChannel: {
        name: 'Test',
    },
    transactions: new EntityCollection(null, null, null, new Criteria(1, 25), [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' },
            },
        },
    ]),
    deliveries: [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' },
            },
        },
    ],
    billingAddress: {
        street: '123 Random street',
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-button': true,
                'sw-label': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-pagination': true,
                'sw-icon': true,
                'sw-data-grid-settings': true,
                'sw-empty-state': true,
                'router-link': {
                    template: '<a><slot></slot></a>',
                },
                'sw-checkbox-field': true,
                'sw-data-grid-skeleton': true,
                'sw-time-ago': true,
                'sw-color-badge': true,
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-bulk-edit-modal': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-sidebar': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
            },
            provide: {
                stateStyleDataProviderService: {
                    getStyle: () => {
                        return {
                            variant: 'success',
                        };
                    },
                },
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve([]) }),
                },
                filterFactory: {},
                searchRankingService: {
                    getSearchFieldsByEntity: () => {
                        return Promise.resolve({
                            name: searchRankingPoint.HIGH_SEARCH_RANKING,
                        });
                    },
                    buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                        return criteria;
                    },
                },
            },
            mocks: {
                $route: { query: '' },
            },
        },

    });
}

Shopware.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria,
    };
});

describe('src/module/sw-order/page/sw-order-list', () => {
    let wrapper;

    it('should be a Vue.js component', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled add button', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should not have an disabled add button', async () => {
        global.activeAclRoles = ['order.creator'];
        wrapper = await createWrapper();
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should contain manual label correctly', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    createdById: '1',
                },
                {
                    ...mockItem,
                },
            ],
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const secondRow = wrapper.find('.sw-data-grid__row--1');

        expect(firstRow.find('.sw-order-list__manual-order-label').exists()).toBeTruthy();
        expect(secondRow.find('.sw-order-list__manual-order-label').exists()).toBeFalsy();
    });

    it('should contain empty customer', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const warningSpy = jest.spyOn(console, 'warn').mockImplementation();

        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    orderCustomer: {
                        customerId: '1',
                        firstName: 'foo',
                        lastName: 'bar',
                    },
                },
                {
                    ...mockItem,
                    orderCustomer: null,
                },
            ],
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const secondRow = wrapper.find('.sw-data-grid__row--1');

        expect(warningSpy).toHaveBeenCalledWith('[[sw-data-grid] Can not resolve accessor: orderCustomer.firstName]');

        expect(firstRow.find('.sw-data-grid__cell--orderCustomer-firstName').exists()).toBeTruthy();
        expect(firstRow.find('.sw-data-grid__cell--orderCustomer-firstName').text()).toBe('bar, foo');

        expect(secondRow.find('.sw-data-grid__cell--orderCustomer-firstName').exists()).toBeTruthy();
        expect(secondRow.find('.sw-data-grid__cell--orderCustomer-firstName').text()).toBe('');
    });

    it('should add query score to the criteria', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
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
        global.activeAclRoles = [];
        wrapper = await createWrapper();
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

    it('should not build query score when search ranking field is null', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
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
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
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
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show correct label for payment status', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        mockItem.transactions = new EntityCollection(null, null, null, new Criteria(1, 25), [
            {
                stateMachineState: {
                    technicalName: 'cancelled',
                    name: 'Cancelled',
                    translated: { name: 'Cancelled' },
                },
            },
            {
                stateMachineState: {
                    technicalName: 'paid',
                    name: 'Paid',
                    translated: { name: 'Paid' },
                },
            },
            {
                stateMachineState: {
                    technicalName: 'open',
                    name: 'Open',
                    translated: { name: 'Open' },
                },
            },
        ]);

        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    createdById: '1',
                },
                {
                    ...mockItem,
                },
            ],
        });

        const firstRow = wrapper.findAll('.sw-data-grid__cell .sw-data-grid__cell-content');
        expect(firstRow.at(21).text()).toBe('Paid');
    });

    it('should push to a new route when editing items', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.$refs.orderGrid.selection = { foo: { deliveries: [] } };
        await wrapper.vm.onBulkEditItems();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith(expect.objectContaining({
            name: 'sw.bulk.edit.order',
            params: expect.objectContaining({
                excludeDelivery: '1',
            }),
        }));


        wrapper.vm.$router.push.mockRestore();
    });

    it('should get list with orderCriteria', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.getLimit()).toBe(25);
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
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('deliveries').hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('transactions').hasAssociation('stateMachineState')).toBe(true);
    });

    it('should contain a computed property, called: listFilterOptions', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm.listFilterOptions).toEqual(expect.objectContaining({
            'affiliate-code-filter': expect.objectContaining({
                property: 'affiliateCode',
                type: 'string-filter',
                label: 'sw-order.filters.affiliateCodeFilter.label',
                placeholder: 'sw-order.filters.affiliateCodeFilter.placeholder',
                valueProperty: 'key',
                labelProperty: 'key',
                options: expect.any(Array),
            }),
            'campaign-code-filter': expect.objectContaining({
                property: 'campaignCode',
                type: 'string-filter',
                label: 'sw-order.filters.campaignCodeFilter.label',
                placeholder: 'sw-order.filters.campaignCodeFilter.placeholder',
                valueProperty: 'key',
                labelProperty: 'key',
                options: expect.any(Array),
            }),
            'promotion-code-filter': expect.objectContaining({
                property: 'lineItems.payload.code',
                type: 'string-filter',
                label: 'sw-order.filters.promotionCodeFilter.label',
                placeholder: 'sw-order.filters.promotionCodeFilter.placeholder',
                valueProperty: 'key',
                labelProperty: 'key',
                options: expect.any(Array),
            }),
        }));
    });

    it('should contain a computed property, called: filterSelectCriteria', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm.filterSelectCriteria).toEqual(expect.objectContaining({
            aggregations: expect.arrayContaining([
                expect.objectContaining({
                    type: 'terms',
                    name: 'affiliateCodes',
                    field: 'affiliateCode',
                    aggregation: null,
                    limit: null,
                    sort: null,
                }),
                expect.objectContaining({
                    type: 'terms',
                    name: 'campaignCodes',
                    field: 'campaignCode',
                    aggregation: null,
                    limit: null,
                    sort: null,
                }),
                expect.objectContaining({
                    type: 'terms',
                    name: 'promotionCodes',
                    field: 'lineItems.payload.code',
                    aggregation: null,
                    limit: null,
                    sort: null,
                }),
            ]),
            page: 1,
            limit: 1,
        }));
    });
});
