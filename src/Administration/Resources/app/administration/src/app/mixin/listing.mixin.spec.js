/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 */
import 'src/app/mixin/listing.mixin';
import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import Bottle from 'bottlejs';
import ApplicationBootstrapper from 'src/core/application';
import VueAdapter from 'src/app/adapter/view/vue.adapter';

let getListMock = jest.fn(() => {});
/* @type VueRouter */
let router;

function createApplication() {
    Bottle.config = { strict: false };

    return new ApplicationBootstrapper(new Bottle());
}

async function createWrapper({
    mocks = {},
    propData = {},
    defaultData = {},
    routeFirstPush = {
        name: 'sw.product.index',
    },
    methods = {
        getList() {
            return getListMock();
        },
    },
} = {}) {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    router = createRouter({
        routes: [
            {
                name: 'sw.product.index',
                path: '/sw/product/index',
                component: { template: '<div></div>' },
            },
            {
                name: 'sw.product.detail',
                path: '/sw/product/detail',
                component: { template: '<div></div>' },
            },
        ],
        history: createWebHashHistory(),
    });

    await router.push(routeFirstPush);

    return mount(
        {
            template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
            mixins: [
                Shopware.Mixin.getByName('listing'),
            ],
            data() {
                return {
                    ...defaultData,
                };
            },
            computed: {
                filters() {
                    return [
                        {
                            name: 'term',
                            property: 'name',
                            label: 'sw-product.list.columnName',
                            active: false,
                        },
                    ];
                },
            },
            methods: {
                ...methods,
            },
        },
        {
            global: {
                plugins: [
                    router,
                ],
                provide: {
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                },
                mocks: {
                    ...mocks,
                },
            },
            props: {
                ...propData,
            },
            attachTo: document.body,
        },
    );
}

async function createRouteWrapper() {
    const vueAdapter = new VueAdapter(createApplication());
    const componentName = `listing-route-component-${Shopware.Utils.createId()}`;

    Shopware.Component.register(componentName, {
        template: '<div class="listing-route-component"></div>',
        name: componentName,
        mixins: [
            Shopware.Mixin.getByName('listing'),
        ],
        computed: {
            filters() {
                return [];
            },
        },
        methods: {
            getList() {
                return getListMock();
            },
        },
    });

    router = createRouter({
        routes: [
            {
                name: 'sw.product.index',
                path: '/sw/product/index',
                component: vueAdapter.getComponentForRoute(componentName),
            },
            {
                name: 'sw.product.detail',
                path: '/sw/product/detail',
                component: {
                    template: '<div class="detail-route-component"></div>',
                },
            },
            {
                name: 'sw.bulk.edit.product',
                path: '/sw/bulk/edit/product',
                component: {
                    template: '<div class="bulk-edit-route-component"></div>',
                },
            },
        ],
        history: createWebHashHistory(),
    });

    await router.push({
        name: 'sw.product.index',
    });

    const wrapper = mount(
        {
            template: '<router-view />',
        },
        {
            global: {
                plugins: [
                    router,
                ],
                provide: {
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                },
            },
            attachTo: document.body,
        },
    );

    await flushPromises();

    return wrapper;
}

describe('src/app/mixin/listing.mixin.ts', () => {
    /* @type Wrapper */
    let wrapper;
    let originalWarn;

    beforeEach(async () => {
        getListMock = jest.fn(() => {});
        wrapper = await createWrapper();

        await flushPromises();

        if (originalWarn) {
            Shopware.Utils.debug.warn = originalWarn;
        } else {
            originalWarn = Shopware.Utils.debug.warn;
        }
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should call getList at created when route information are provided', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            routeFirstPush: {
                name: 'sw.product.index',
                query: {
                    page: 7,
                    limit: 100,
                    naturalSorting: 'true',
                },
            },
        });

        expect(getListMock).toHaveBeenCalledWith();
        expect(wrapper.vm.page).toBe(7);
        expect(wrapper.vm.limit).toBe(100);
        expect(wrapper.vm.naturalSorting).toBe(true);
    });

    it('should convert boolean route values as string to boolean values (true)', async () => {
        await router.push({
            query: {
                naturalSorting: 'true',
            },
        });

        expect(wrapper.vm.naturalSorting).toBe(true);
    });

    it('should convert boolean route values as string to boolean values (false)', async () => {
        await router.push({
            query: {
                naturalSorting: 'false',
            },
        });

        expect(wrapper.vm.naturalSorting).toBe(false);
    });

    it('should call getList at created when disableRouteParams is set', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: true,
            },
        });

        expect(getListMock).toHaveBeenCalledWith();
        // use fallback data values
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.limit).toBe(25);
        expect(wrapper.vm.naturalSorting).toBe(false);
    });

    it('should not watch for route changes when disableRouteParams is set', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: true,
            },
        });

        getListMock.mockClear();

        // simulate route change
        router.push({
            query: {
                page: 8,
            },
        });

        expect(getListMock).not.toHaveBeenCalledWith();
    });

    it('should watch for route changes', async () => {
        // push to a route with search params
        await router.push({
            query: {
                page: 7,
                limit: 100,
                naturalSorting: 'true',
            },
        });
        getListMock.mockClear();

        // simulate route change
        router.push({
            query: {
                page: 8,
            },
        });

        await flushPromises();

        expect(getListMock).toHaveBeenCalledWith();
    });

    it('should clear app selections when leaving a route-loaded listing component', async () => {
        await wrapper.unmount();
        wrapper = undefined;

        const routeWrapper = await createRouteWrapper();

        Shopware.Store.get('shopwareApps').selectedIds = ['order-id'];
        Shopware.Store.get('swBulkEdit').selectedIds = ['order-id'];

        await router.push({
            name: 'sw.product.detail',
        });

        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([]);
        expect(Shopware.Store.get('swBulkEdit').selectedIds).toEqual([]);

        await routeWrapper.unmount();
    });

    it('should keep app selections when navigating from a route-loaded listing component to bulk edit', async () => {
        await wrapper.unmount();
        wrapper = undefined;

        const routeWrapper = await createRouteWrapper();

        Shopware.Store.get('shopwareApps').selectedIds = ['product-id'];
        Shopware.Store.get('swBulkEdit').selectedIds = ['product-id'];

        await router.push({
            name: 'sw.bulk.edit.product',
        });

        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual(['product-id']);
        expect(Shopware.Store.get('swBulkEdit').selectedIds).toEqual(['product-id']);

        await routeWrapper.unmount();
    });

    it('should set freshSearchTerm to true when "term" changes', async () => {
        expect(wrapper.vm.freshSearchTerm).toBe(false);

        await wrapper.setData({
            term: 'test',
        });

        expect(wrapper.vm.freshSearchTerm).toBe(true);
    });

    it('should set freshSearchTerm to false when "term" is cleared', async () => {
        await wrapper.setData({
            term: 'test',
        });

        expect(wrapper.vm.freshSearchTerm).toBe(true);

        await wrapper.setData({
            term: '',
        });

        expect(wrapper.vm.freshSearchTerm).toBe(false);
    });

    it('should set freshSearchTerm to false when "sortBy" changes', async () => {
        await wrapper.setData({
            term: 'test',
        });

        expect(wrapper.vm.freshSearchTerm).toBe(true);

        await wrapper.setData({
            sortBy: 'test',
        });

        expect(wrapper.vm.freshSearchTerm).toBe(false);
    });

    it('should set freshSearchTerm to false when "sortDirection" changes', async () => {
        await wrapper.setData({
            term: 'test',
        });

        expect(wrapper.vm.freshSearchTerm).toBe(true);

        await wrapper.setData({
            sortDirection: 'test',
        });

        expect(wrapper.vm.freshSearchTerm).toBe(false);
    });

    it('should contain correct listing params', async () => {
        await router.push({
            query: {
                page: 7,
                limit: 100,
                naturalSorting: 'true',
                sortBy: 'name',
                sortDirection: 'ASC',
                term: 'Fooooo',
            },
        });

        expect(wrapper.vm.getMainListingParams()).toEqual({
            page: '7',
            limit: '100',
            naturalSorting: true,
            sortBy: 'name',
            sortDirection: 'ASC',
            term: 'Fooooo',
        });
    });

    it('should contain correct listing params when route params are disabled', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: true,
            },
        });

        await wrapper.setData({
            page: 7,
            limit: 100,
            naturalSorting: true,
            sortBy: 'name',
            sortDirection: 'ASC',
            term: 'Fooooo',
        });

        expect(wrapper.vm.getMainListingParams()).toEqual({
            page: 7,
            limit: 100,
            naturalSorting: true,
            sortBy: 'name',
            sortDirection: 'ASC',
            term: 'Fooooo',
        });
    });

    it('should update the selection correctly on updateSelection', async () => {
        wrapper.vm.updateSelection({
            foo: 'bar',
        });

        expect(wrapper.vm.selection).toEqual({
            foo: 'bar',
        });
    });

    it('should update the route and data correctly on onPageChange', async () => {
        wrapper.vm.onPageChange({
            page: 14,
            limit: 100,
        });
        await flushPromises();

        expect(wrapper.vm.page).toBe(14);
        expect(wrapper.vm.limit).toBe(100);
        expect(router.currentRoute.value.query.page).toBe('14');
    });

    it('should update the data correctly on onPageChange with disabled route params', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: true,
            },
        });

        getListMock.mockClear();

        expect(getListMock).not.toHaveBeenCalledWith();
        wrapper.vm.onPageChange({
            page: 14,
            limit: 100,
        });

        expect(wrapper.vm.page).toBe(14);
        expect(wrapper.vm.limit).toBe(100);
        expect(getListMock).toHaveBeenCalledWith();
    });

    it('should update the data correctly on onSearch with disabled route params', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: true,
                page: 2,
            },
        });

        getListMock.mockClear();

        expect(wrapper.vm.page).toBe(2);

        expect(getListMock).not.toHaveBeenCalledWith();
        wrapper.vm.onSearch('new search value');

        expect(wrapper.vm.term).toBe('new search value');
        expect(wrapper.vm.page).toBe(1);
        expect(getListMock).toHaveBeenCalledWith();
    });

    it('should update the route and data correctly on onSearch', async () => {
        wrapper.vm.onSearch('new search value');
        await flushPromises();

        expect(wrapper.vm.term).toBe('new search value');
        expect(wrapper.vm.page).toBe(1);
        expect(router.currentRoute.value.query.term).toBe('new search value');
        expect(router.currentRoute.value.query.page).toBe('1');
    });

    it('should update the data correctly on onSwitchFilter', async () => {
        wrapper.vm.onSwitchFilter(undefined, 0);

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.filters[0]).toEqual({
            name: 'term',
            property: 'name',
            label: 'sw-product.list.columnName',
            active: true,
        });
    });

    it('should update the data correctly on onSort (disableRouteParams true)', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: true,
            },
        });

        getListMock.mockClear();

        wrapper.vm.onSort({
            sortBy: 'name',
            sortDirection: 'DESC',
        });

        expect(wrapper.vm.sortBy).toBe('name');
        expect(wrapper.vm.sortDirection).toBe('DESC');
    });

    it('should update the route correctly on onSort (disableRouteParams false)', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: false,
            },
        });

        getListMock.mockClear();

        wrapper.vm.onSort({
            sortBy: 'name',
            sortDirection: 'DESC',
        });

        await flushPromises();

        expect(router.currentRoute.value.query).toEqual(
            expect.objectContaining({
                sortBy: 'name',
                sortDirection: 'DESC',
            }),
        );
    });

    it('should update the data correctly on onSortColumn (disableRouteParams true)', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: true,
            },
        });

        wrapper.vm.sortDirection = 'ASC';

        getListMock.mockClear();

        // first click sort ascending
        wrapper.vm.onSortColumn({
            dataIndex: 'date',
            naturalSorting: true,
        });

        expect(wrapper.vm.sortBy).toBe('date');
        expect(wrapper.vm.sortDirection).toBe('ASC');

        // second click sort descending
        wrapper.vm.onSortColumn({
            dataIndex: 'date',
            naturalSorting: true,
        });

        expect(wrapper.vm.sortBy).toBe('date');
        expect(wrapper.vm.sortDirection).toBe('DESC');
    });

    it('should update the route correctly on onSortColumn (disableRouteParams false)', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({
            defaultData: {
                disableRouteParams: false,
            },
        });

        wrapper.vm.sortDirection = 'ASC';

        getListMock.mockClear();

        // first click sort ascending
        wrapper.vm.onSortColumn({
            dataIndex: 'date',
            naturalSorting: true,
        });

        await flushPromises();

        expect(router.currentRoute.value.query).toEqual(
            expect.objectContaining({
                sortBy: 'date',
                sortDirection: 'ASC',
            }),
        );

        await flushPromises();

        // second click sort descending
        wrapper.vm.onSortColumn({
            dataIndex: 'date',
            naturalSorting: true,
        });

        await flushPromises();

        expect(router.currentRoute.value.query).toEqual(
            expect.objectContaining({
                sortBy: 'date',
                sortDirection: 'DESC',
            }),
        );
    });

    it('should call getList on onRefresh', async () => {
        await wrapper.unmount();

        getListMock = jest.fn(() => {});
        wrapper = await createWrapper({});

        getListMock.mockClear();

        wrapper.vm.onRefresh();

        expect(getListMock).toHaveBeenCalled();
    });

    it('should throw a console warning when no getList method ist defined', async () => {
        await wrapper.unmount();

        Shopware.Utils.debug.warn = jest.fn(() => {});

        wrapper = await createWrapper({
            methods: {},
        });

        wrapper.vm.getList();

        expect(Shopware.Utils.debug.warn).toHaveBeenCalledWith(
            'Listing Mixin',
            'When using the listing mixin you have to implement your custom "getList()" method.',
        );
    });

    it('should return true when isValidTerm is used with trimmed term >= 1 length', async () => {
        expect(wrapper.vm.isValidTerm('test  ')).toBe(true);
        expect(wrapper.vm.isValidTerm('9')).toBe(true);
    });

    it('should return false when isValidTerm is shorter than 1 length', async () => {
        expect(wrapper.vm.isValidTerm(' ')).toBe(false);
    });

    it('should have the correct maxPage computed value', async () => {
        wrapper.vm.total = 275;
        wrapper.vm.limit = 25;

        expect(wrapper.vm.maxPage).toBe(11);
    });

    it('should have the correct maxPage computed value when total is 0', async () => {
        wrapper.vm.total = 0;
        wrapper.vm.limit = 25;

        expect(wrapper.vm.maxPage).toBe(0);
    });

    it('should have the correct routeName computed value', async () => {
        expect(wrapper.vm.routeName).toBe('sw.product.index');

        await router.push({
            name: 'sw.product.detail',
        });

        expect(wrapper.vm.routeName).toBe('sw.product.detail');
    });

    it('should have the correct selectionArray computed value', async () => {
        wrapper.vm.selection = {
            1: {
                id: 1,
            },
            2: {
                id: 2,
            },
        };

        expect(JSON.stringify(wrapper.vm.selectionArray)).toBe(
            JSON.stringify([
                { id: 1 },
                { id: 2 },
            ]),
        );
    });

    it('should have the correct selectionCount computed value', async () => {
        wrapper.vm.selection = {
            1: {
                id: 1,
            },
            2: {
                id: 2,
            },
        };

        expect(wrapper.vm.selectionCount).toBe(2);
    });

    describe('updateCriteria', () => {
        it('should reset page to 1 when updateCriteria is called', async () => {
            wrapper.vm.page = 5;

            const newCriteria = [{ type: 'equals', field: 'active', value: true }];
            wrapper.vm.updateCriteria(newCriteria);

            expect(wrapper.vm.page).toBe(1);
        });

        it('should call updateRoute with page 1 when updateCriteria is called', async () => {
            wrapper.vm.page = 5;
            wrapper.vm.disableRouteParams = false;

            wrapper.vm.updateRoute = jest.fn();

            const newCriteria = [{ type: 'equals', field: 'active', value: true }];
            wrapper.vm.updateCriteria(newCriteria);

            expect(wrapper.vm.updateRoute).toHaveBeenCalledWith({ page: 1 });

            wrapper.vm.updateRoute.mockRestore();
        });

        it('should call getList directly when updateCriteria is called and disableRouteParams is true', async () => {
            wrapper.vm.page = 5;
            wrapper.vm.disableRouteParams = true;

            wrapper.vm.getList = jest.fn();
            wrapper.vm.updateRoute = jest.fn();

            const newCriteria = [{ type: 'equals', field: 'active', value: true }];
            wrapper.vm.updateCriteria(newCriteria);

            expect(wrapper.vm.getList).toHaveBeenCalled();
            expect(wrapper.vm.updateRoute).not.toHaveBeenCalled();

            wrapper.vm.getList.mockRestore();
            wrapper.vm.updateRoute.mockRestore();
        });

        it('should set filterCriteria when updateCriteria is called', async () => {
            const newCriteria = [{ type: 'equals', field: 'active', value: true }];
            wrapper.vm.updateCriteria(newCriteria);

            expect(wrapper.vm.filterCriteria).toStrictEqual(newCriteria);
        });
    });
});
