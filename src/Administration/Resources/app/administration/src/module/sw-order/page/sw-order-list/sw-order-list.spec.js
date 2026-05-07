import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';

/**
 * @sw-package checkout
 */

const mockItem = {
    orderNumber: '1',
    orderCustomer: {
        customerId: '2',
    },
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
    primaryOrderTransaction: {
        stateMachineState: {
            technicalName: 'open',
            name: 'Open',
            translated: { name: 'Open' },
        },
    },
    primaryOrderDelivery: {
        stateMachineState: {
            technicalName: 'open',
            name: 'Open',
            translated: { name: 'Open' },
        },
        shippingOrderAddress: {
            street: '123 Random street',
            zipcode: '12345',
            city: 'Random City',
        },
    },
    billingAddress: {
        street: '123 Random street',
        zipcode: '12345',
        city: 'Random City',
    },
};

if (!Shopware.Feature.isActive('v6.8.0.0')) {
    mockItem.addresses = [
        {
            street: '123 Random street',
        },
    ];
    mockItem.transactions = new EntityCollection(null, null, null, new Criteria(1, 25), [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' },
            },
        },
    ]);
    mockItem.deliveries = [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' },
            },
        },
    ];
}

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
                'sw-label': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-pagination': true,
                'sw-data-grid-settings': true,
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
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
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
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
        },
    });
}

function createTransactionCollection(transactions) {
    return new EntityCollection(null, null, null, new Criteria(1, 25), transactions);
}

Shopware.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria,
    };
});

describe('src/module/sw-order/page/sw-order-list', () => {
    let wrapper;

    it('should have an disabled add button', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const addButton = wrapper.find('.sw-order-list__add-order');

        expect(addButton.attributes('disabled')).toBeDefined();
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
            total: 2,
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const secondRow = wrapper.find('.sw-data-grid__row--1');

        expect(firstRow.find('.sw-order-list__manual-order-label').exists()).toBeTruthy();
        expect(secondRow.find('.sw-order-list__manual-order-label').exists()).toBeFalsy();
    });

    it('should render comment tooltip buttons depending on available comments', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    customerComment: 'Customer comment',
                    internalComment: 'Internal comment',
                },
                {
                    ...mockItem,
                    customerComment: 'Customer comment',
                    internalComment: null,
                },
                {
                    ...mockItem,
                    customerComment: null,
                    internalComment: 'Internal comment',
                },
            ],
            total: 3,
        });

        const firstRowButtons = wrapper.find('.sw-data-grid__row--0').findAll('.sw-order-list__tooltip-order-comment');
        const secondRowButtons = wrapper.find('.sw-data-grid__row--1').findAll('.sw-order-list__tooltip-order-comment');
        const thirdRowButtons = wrapper.find('.sw-data-grid__row--2').findAll('.sw-order-list__tooltip-order-comment');

        expect(firstRowButtons).toHaveLength(2);
        expect(firstRowButtons.at(0).classes()).toContain('mt-button--secondary');
        expect(firstRowButtons.at(1).classes()).toContain('mt-button--primary');
        expect(secondRowButtons).toHaveLength(1);
        expect(secondRowButtons.at(0).classes()).toContain('mt-button--secondary');
        expect(thirdRowButtons).toHaveLength(1);
        expect(thirdRowButtons.at(0).classes()).toContain('mt-button--primary');
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
            total: 2,
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

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.mt-empty-state')).toBeTruthy();
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show correct label for payment status', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        mockItem.primaryOrderTransaction = {
            stateMachineState: {
                technicalName: 'paid',
                name: 'Paid',
                translated: { name: 'Paid' },
            },
        };

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
            total: 2,
        });

        const firstRow = wrapper.findAll('.sw-data-grid__cell .sw-data-grid__cell-content');
        expect(firstRow.at(22).text()).toBe('Paid');
    });

    it('should push to a new route when editing items', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        await wrapper.setData({
            total: 2,
        });
        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.$refs.orderGrid.selection = { foo: { deliveries: [] } };
        await wrapper.vm.onBulkEditItems();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'sw.bulk.edit.order',
                params: expect.objectContaining({
                    excludeDelivery: '1',
                }),
            }),
        );

        wrapper.vm.$router.push.mockRestore();
    });

    it('should get list with orderCriteria', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.getLimit()).toBe(25);
        [
            'billingAddress',
            'salesChannel',
            'orderCustomer',
            'currency',
            'documents',
            'stateMachineState',
            'primaryOrderTransaction',
            'primaryOrderDelivery',
        ].forEach((association) => expect(criteria.hasAssociation(association)).toBe(true));
    });

    it('should add associations no longer autoload in the orderCriteria', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.getAssociation('primaryOrderDelivery').hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('primaryOrderDelivery').hasAssociation('shippingOrderAddress')).toBe(true);
        expect(criteria.getAssociation('primaryOrderTransaction').hasAssociation('stateMachineState')).toBe(true);
    });

    it('should only load primary order associations when v6.8.0.0 is active', async () => {
        global.activeAclRoles = [];
        global.activeFeatureFlags = ['v6.8.0.0'];

        try {
            wrapper = await createWrapper();
            const criteria = wrapper.vm.orderCriteria;

            expect(criteria.hasAssociation('primaryOrderDelivery')).toBe(true);
            expect(criteria.hasAssociation('primaryOrderTransaction')).toBe(true);
            expect(criteria.hasAssociation('deliveries')).toBe(false);
            expect(criteria.hasAssociation('transactions')).toBe(false);
            expect(criteria.hasAssociation('addresses')).toBe(false);
        } finally {
            global.activeFeatureFlags = [];
        }
    });

    it('should contain a computed property, called: listFilterOptions', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm.listFilterOptions).toEqual(
            expect.objectContaining({
                'affiliate-code-filter': expect.objectContaining({
                    property: 'affiliateCode',
                    type: 'string-filter',
                    label: 'sw-order.filters.affiliateCodeFilter.label',
                    placeholder: 'sw-order.filters.affiliateCodeFilter.placeholder',
                    valueProperty: 'key',
                    labelProperty: 'key',
                }),
                'campaign-code-filter': expect.objectContaining({
                    property: 'campaignCode',
                    type: 'string-filter',
                    label: 'sw-order.filters.campaignCodeFilter.label',
                    placeholder: 'sw-order.filters.campaignCodeFilter.placeholder',
                    valueProperty: 'key',
                    labelProperty: 'key',
                }),
                'promotion-code-filter': expect.objectContaining({
                    property: 'lineItems.payload.code',
                    type: 'string-filter',
                    label: 'sw-order.filters.promotionCodeFilter.label',
                    placeholder: 'sw-order.filters.promotionCodeFilter.placeholder',
                    valueProperty: 'key',
                    labelProperty: 'key',
                }),
            }),
        );
    });

    it('should contain a computed property, called: filterSelectCriteria', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.vm.filterSelectCriteria).toEqual(
            expect.objectContaining({
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
            }),
        );
    });

    it('should consider criteria filters via updateCriteria', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const filter = Criteria.equals('foo', 'bar');
        wrapper.vm.updateCriteria([filter]);
        await flushPromises();

        expect(wrapper.vm.filterCriteria).toContainEqual(filter);
    });

    it('should render the delivery address from primaryOrderDelivery', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    primaryOrderDelivery: {
                        stateMachineState: {
                            technicalName: 'open',
                            translated: { name: 'Open' },
                        },
                        shippingOrderAddress: {
                            street: '742 Evergreen Terrace',
                            zipcode: '99999',
                            city: 'Springfield',
                        },
                    },
                },
            ],
            total: 1,
        });

        const html = wrapper.html();
        expect(html).toContain('742 Evergreen Terrace');
        expect(html).toContain('Springfield');
        expect(html).toContain('99999');
    });

    it('should render the delivery state from primaryOrderDelivery', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    primaryOrderDelivery: {
                        stateMachineState: {
                            technicalName: 'shipped',
                            translated: { name: 'Shipped' },
                        },
                        shippingOrderAddress: mockItem.primaryOrderDelivery.shippingOrderAddress,
                    },
                },
            ],
            total: 1,
        });

        const stateCells = wrapper.findAll('.sw-order-list__state');
        const stateTexts = stateCells.map((cell) => cell.text());
        expect(stateTexts).toContain('Shipped');
    });

    it('should render the transaction state from primaryOrderTransaction', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            orders: [
                {
                    ...mockItem,
                    primaryOrderTransaction: {
                        stateMachineState: {
                            technicalName: 'paid',
                            translated: { name: 'Paid' },
                        },
                    },
                },
            ],
            total: 1,
        });

        const stateCells = wrapper.findAll('.sw-order-list__state');
        const stateTexts = stateCells.map((cell) => cell.text());
        expect(stateTexts).toContain('Paid');
    });

    it('should not fall back to deliveries and transactions when v6.8.0.0 is active', async () => {
        global.activeAclRoles = [];
        global.activeFeatureFlags = ['v6.8.0.0'];

        try {
            wrapper = await createWrapper();

            const order = {
                primaryOrderDelivery: null,
                primaryOrderTransaction: null,
                deliveries: [
                    {
                        stateMachineState: {
                            technicalName: 'shipped',
                            translated: { name: 'Fallback Shipped' },
                        },
                    },
                ],
                transactions: new EntityCollection(null, null, null, new Criteria(1, 25), [
                    {
                        stateMachineState: {
                            technicalName: 'paid',
                            translated: { name: 'Fallback Paid' },
                        },
                    },
                ]),
            };

            expect(wrapper.vm.getDelivery(order)).toBeNull();
            expect(wrapper.vm.transaction(order)).toBeNull();
        } finally {
            global.activeFeatureFlags = [];
        }
    });

    /**
     * @deprecated tag:v6.8.0 - test will be removed when the 6.7 fallback is dropped
     */
    if (!Shopware.Feature.isActive('v6.8.0.0')) {
        it('should fall back to deliveries[0] address when primaryOrderDelivery is null (DAL writes without primaryOrderDeliveryId)', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            await wrapper.setData({
                orders: [
                    {
                        ...mockItem,
                        primaryOrderDelivery: null,
                        deliveries: [
                            {
                                stateMachineState: {
                                    technicalName: 'open',
                                    translated: { name: 'Open' },
                                },
                                shippingOrderAddress: {
                                    street: 'Fallback Street 1',
                                    zipcode: '54321',
                                    city: 'Fallback City',
                                },
                            },
                        ],
                    },
                ],
                total: 1,
            });

            const html = wrapper.html();
            expect(html).toContain('Fallback Street 1');
            expect(html).toContain('Fallback City');
            expect(html).toContain('54321');
        });

        it('should fall back to the first delivery when multiple deliveries exist without primaryOrderDelivery', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            await wrapper.setData({
                orders: [
                    {
                        ...mockItem,
                        primaryOrderDelivery: null,
                        deliveries: [
                            {
                                stateMachineState: {
                                    technicalName: 'open',
                                    translated: { name: 'Open' },
                                },
                                shippingOrderAddress: {
                                    street: 'First Fallback Street',
                                    zipcode: '11111',
                                    city: 'First City',
                                },
                            },
                            {
                                stateMachineState: {
                                    technicalName: 'shipped',
                                    translated: { name: 'Shipped' },
                                },
                                shippingOrderAddress: {
                                    street: 'Second Fallback Street',
                                    zipcode: '22222',
                                    city: 'Second City',
                                },
                            },
                        ],
                    },
                ],
                total: 1,
            });

            const html = wrapper.html();
            expect(html).toContain('First Fallback Street');
            expect(html).not.toContain('Second Fallback Street');
        });

        it('should fall back to deliveries[0] state when primaryOrderDelivery is null', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            await wrapper.setData({
                orders: [
                    {
                        ...mockItem,
                        primaryOrderDelivery: null,
                        deliveries: [
                            {
                                stateMachineState: {
                                    technicalName: 'shipped',
                                    translated: { name: 'Fallback Shipped' },
                                },
                                shippingOrderAddress: mockItem.primaryOrderDelivery.shippingOrderAddress,
                            },
                        ],
                    },
                ],
                total: 1,
            });

            const stateCells = wrapper.findAll('.sw-order-list__state');
            const stateTexts = stateCells.map((cell) => cell.text());
            expect(stateTexts).toContain('Fallback Shipped');
        });

        it('should fall back to transactions[0] state when primaryOrderTransaction is null', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            await wrapper.setData({
                orders: [
                    {
                        ...mockItem,
                        primaryOrderTransaction: null,
                        transactions: new EntityCollection(null, null, null, new Criteria(1, 25), [
                            {
                                stateMachineState: {
                                    technicalName: 'paid',
                                    translated: { name: 'Fallback Paid' },
                                },
                            },
                        ]),
                    },
                ],
                total: 1,
            });

            const stateCells = wrapper.findAll('.sw-order-list__state');
            const stateTexts = stateCells.map((cell) => cell.text());
            expect(stateTexts).toContain('Fallback Paid');
        });

        it('should fall back to the first transaction that is not cancelled or failed', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            await wrapper.setData({
                orders: [
                    {
                        ...mockItem,
                        primaryOrderTransaction: null,
                        transactions: createTransactionCollection([
                            {
                                stateMachineState: {
                                    technicalName: 'cancelled',
                                    translated: { name: 'Fallback Cancelled' },
                                },
                            },
                            {
                                stateMachineState: {
                                    technicalName: 'paid',
                                    translated: { name: 'Fallback Paid' },
                                },
                            },
                            {
                                stateMachineState: {
                                    technicalName: 'open',
                                    translated: { name: 'Fallback Open' },
                                },
                            },
                        ]),
                    },
                ],
                total: 1,
            });

            const stateCells = wrapper.findAll('.sw-order-list__state');
            const stateTexts = stateCells.map((cell) => cell.text());
            expect(stateTexts).toContain('Fallback Paid');
            expect(stateTexts).not.toContain('Fallback Cancelled');
        });

        it('should fall back to the last transaction when all transactions are cancelled or failed', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            await wrapper.setData({
                orders: [
                    {
                        ...mockItem,
                        primaryOrderTransaction: null,
                        transactions: createTransactionCollection([
                            {
                                stateMachineState: {
                                    technicalName: 'cancelled',
                                    translated: { name: 'Fallback Cancelled' },
                                },
                            },
                            {
                                stateMachineState: {
                                    technicalName: 'failed',
                                    translated: { name: 'Fallback Failed' },
                                },
                            },
                        ]),
                    },
                ],
                total: 1,
            });

            const stateCells = wrapper.findAll('.sw-order-list__state');
            const stateTexts = stateCells.map((cell) => cell.text());
            expect(stateTexts).toContain('Fallback Failed');
        });

        it('should render no transaction state when both primaryOrderTransaction and transactions are missing', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            const order = {
                ...mockItem,
                primaryOrderTransaction: null,
                transactions: [],
            };

            expect(wrapper.vm.transaction(order)).toBeNull();
            expect(wrapper.vm.getTransactionState(order)).toBeNull();
        });

        it('should render no delivery address when both primaryOrderDelivery and deliveries are missing', async () => {
            global.activeAclRoles = [];
            wrapper = await createWrapper();

            await wrapper.setData({
                orders: [
                    {
                        ...mockItem,
                        primaryOrderDelivery: null,
                        deliveries: [],
                    },
                ],
                total: 1,
            });

            const html = wrapper.html();
            expect(html).not.toContain('123 Random street');
        });
    }
});
