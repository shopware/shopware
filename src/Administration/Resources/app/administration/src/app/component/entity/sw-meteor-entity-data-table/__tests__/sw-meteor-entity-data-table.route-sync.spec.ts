/**
 * @sw-package framework
 */

import { reactive } from 'vue';
import {
    createSearchResult,
    createWrapper,
    firstSearchCriteria,
    lastSearchCriteria,
    mountedTable,
} from './sw-meteor-entity-data-table.test-utils';

type RouteGuard = (this: unknown, to: { query: Record<string, string> }, from: unknown, next: () => void) => void;

type RouteAwareVm = {
    $options: {
        beforeRouteUpdate?: RouteGuard | RouteGuard[];
    };
};

type RouteUpdate = {
    query: Record<string, unknown>;
};

describe('src/app/component/entity/sw-meteor-entity-data-table route synchronization', () => {
    it('hydrates table state from the route query before the first repository search', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {
                preservedParam: 'value',
            },
            query: {
                page: '3',
                limit: '50',
                term: 'route search',
                sortBy: 'name',
                sortDirection: 'DESC',
                naturalSorting: 'true',
                keep: 'yes',
            },
        });
        const router = {
            push: jest.fn(),
            replace: jest.fn(),
        };

        const wrapper = await createWrapper(
            {
                repository,
                defaultSortBy: 'createdAt',
            },
            {
                mocks: {
                    $route: route,
                    $router: router,
                },
            },
        );

        expect(wrapper.vm.state).toEqual({
            page: 3,
            limit: 50,
            searchTerm: 'route search',
            sortBy: 'name',
            sortDirection: 'DESC',
            naturalSorting: true,
        });
        expect(firstSearchCriteria(repository)).toEqual(
            expect.objectContaining({
                page: 3,
                limit: 50,
                term: 'route search',
            }),
        );
        expect(router.replace).not.toHaveBeenCalled();
        expect(wrapper.emitted('search-term-change')?.[0]?.[0]).toBe('route search');
    });

    it('normalizes an empty route query with table defaults by replacing the route', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {},
            query: {},
        });
        const router = {
            push: jest.fn(),
            replace: jest.fn(),
        };

        await createWrapper(
            {
                repository,
                defaultSortBy: 'name',
            },
            {
                mocks: {
                    $route: route,
                    $router: router,
                },
            },
        );

        expect(router.replace).toHaveBeenCalledWith({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                page: 1,
                limit: 25,
                term: '',
                sortBy: 'name',
                sortDirection: 'ASC',
                naturalSorting: false,
            },
        });
    });

    it('preserves unrelated query values when pagination updates the route', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                keep: 'yes',
                page: '1',
                limit: '25',
                term: '',
                sortBy: '',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
        const router = {
            push: jest.fn(),
            replace: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                repository,
            },
            {
                mocks: {
                    $route: route,
                    $router: router,
                },
            },
        );

        await mountedTable(wrapper).vm.$emit('pagination-current-page-change', 3);
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                keep: 'yes',
                page: 3,
                limit: 25,
                term: '',
                sortBy: '',
                sortDirection: 'ASC',
                naturalSorting: false,
            },
        });
    });

    it('updates route query values for page size, sort, and embedded search changes', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                page: '1',
                limit: '25',
                term: '',
                sortBy: '',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
        const router = {
            push: jest.fn(),
            replace: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                repository,
                columns: [
                    {
                        property: 'name',
                        label: 'Name',
                        naturalSorting: true,
                    },
                ],
            },
            {
                mocks: {
                    $route: route,
                    $router: router,
                },
            },
        );

        await mountedTable(wrapper).vm.$emit('pagination-limit-change', 50);
        await flushPromises();
        await mountedTable(wrapper).vm.$emit('sort-change', 'name', 'DESC');
        await flushPromises();
        await mountedTable(wrapper).vm.$emit('search-value-change', 'meteor');
        await flushPromises();

        const pushedRoutes = router.push.mock.calls as Array<[RouteUpdate]>;

        expect(repository.search).toHaveBeenCalledTimes(4);
        expect(pushedRoutes[0]?.[0].query).toEqual(
            expect.objectContaining({
                page: 1,
                limit: 50,
            }),
        );
        expect(pushedRoutes[1]?.[0].query).toEqual(
            expect.objectContaining({
                sortBy: 'name',
                sortDirection: 'DESC',
                naturalSorting: true,
            }),
        );
        expect(pushedRoutes[2]?.[0].query).toEqual(
            expect.objectContaining({
                page: 1,
                term: 'meteor',
            }),
        );
        expect(lastSearchCriteria(repository).term).toBe('meteor');
        expect(wrapper.emitted('search-term-change')?.at(-1)?.[0]).toBe('meteor');
    });

    it('hydrates external route query changes and reloads once', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                page: '1',
                limit: '25',
                term: '',
                sortBy: '',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
        const router = {
            push: jest.fn(),
            replace: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                repository,
            },
            {
                mocks: {
                    $route: route,
                    $router: router,
                },
            },
        );

        const beforeRouteUpdate = (wrapper.vm as unknown as RouteAwareVm).$options.beforeRouteUpdate;
        const routeGuard = Array.isArray(beforeRouteUpdate) ? beforeRouteUpdate[0] : beforeRouteUpdate;
        const next = jest.fn();

        routeGuard?.call(
            wrapper.vm,
            {
                query: {
                    page: '4',
                    limit: '10',
                    term: 'external',
                    sortBy: 'name',
                    sortDirection: 'DESC',
                    naturalSorting: 'true',
                },
            },
            route,
            next,
        );
        await flushPromises();

        expect(next).toHaveBeenCalled();
        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.state).toEqual({
            page: 4,
            limit: 10,
            searchTerm: 'external',
            sortBy: 'name',
            sortDirection: 'DESC',
            naturalSorting: true,
        });
        expect(lastSearchCriteria(repository).term).toBe('external');
        expect(wrapper.emitted('search-term-change')?.at(-1)?.[0]).toBe('external');
    });

    it('clears the search term when an external route update contains an empty term value', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                page: '1',
                limit: '25',
                term: 'route search',
                sortBy: '',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
        const wrapper = await createWrapper(
            {
                repository,
                initialSearchTerm: 'initial search',
            },
            {
                mocks: {
                    $route: route,
                },
            },
        );

        const beforeRouteUpdate = (wrapper.vm as unknown as RouteAwareVm).$options.beforeRouteUpdate;
        const routeGuard = Array.isArray(beforeRouteUpdate) ? beforeRouteUpdate[0] : beforeRouteUpdate;

        routeGuard?.call(
            wrapper.vm,
            {
                query: {
                    page: '1',
                    limit: '25',
                    term: '',
                    sortBy: '',
                    sortDirection: 'ASC',
                    naturalSorting: 'false',
                },
            },
            route,
            jest.fn(),
        );
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.state.searchTerm).toBe('');
        expect(lastSearchCriteria(repository).term).toBeNull();
        expect(wrapper.emitted('search-term-change')?.at(-1)?.[0]).toBe('');
    });

    it('uses the configured default search term when an external route update omits the term key', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                page: '1',
                limit: '25',
                term: 'route search',
                sortBy: '',
                sortDirection: 'ASC',
                naturalSorting: 'false',
            },
        });
        const wrapper = await createWrapper(
            {
                repository,
                defaultSearchTerm: 'configured default',
            },
            {
                mocks: {
                    $route: route,
                },
            },
        );

        const beforeRouteUpdate = (wrapper.vm as unknown as RouteAwareVm).$options.beforeRouteUpdate;
        const routeGuard = Array.isArray(beforeRouteUpdate) ? beforeRouteUpdate[0] : beforeRouteUpdate;

        routeGuard?.call(
            wrapper.vm,
            {
                query: {
                    page: '1',
                    limit: '25',
                    sortBy: '',
                    sortDirection: 'ASC',
                    naturalSorting: 'false',
                },
            },
            route,
            jest.fn(),
        );
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.state.searchTerm).toBe('configured default');
        expect(lastSearchCriteria(repository).term).toBe('configured default');
    });

    it('hydrates and writes custom route query keys', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const route = reactive({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                p: '2',
                l: '10',
                q: 'custom route search',
                orderBy: 'name',
                order: 'DESC',
                natural: 'true',
                keep: 'yes',
            },
        });
        const router = {
            push: jest.fn(),
            replace: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                repository,
                routeQueryKeys: {
                    page: 'p',
                    limit: 'l',
                    term: 'q',
                    sortBy: 'orderBy',
                    sortDirection: 'order',
                    naturalSorting: 'natural',
                },
            },
            {
                mocks: {
                    $route: route,
                    $router: router,
                },
            },
        );

        expect(wrapper.vm.state).toEqual({
            page: 2,
            limit: 10,
            searchTerm: 'custom route search',
            sortBy: 'name',
            sortDirection: 'DESC',
            naturalSorting: true,
        });

        await mountedTable(wrapper).vm.$emit('search-value-change', 'next search');
        await flushPromises();

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.manufacturer.index',
            params: {},
            query: {
                p: 1,
                l: 10,
                q: 'next search',
                orderBy: 'name',
                order: 'DESC',
                natural: true,
                keep: 'yes',
            },
        });
    });

    it('does not write route query values when route synchronization is disabled', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const router = {
            push: jest.fn(),
            replace: jest.fn(),
        };
        const wrapper = await createWrapper(
            {
                repository,
                syncRouteQuery: false,
            },
            {
                mocks: {
                    $route: {
                        name: 'sw.manufacturer.index',
                        params: {},
                        query: {},
                    },
                    $router: router,
                },
            },
        );

        await mountedTable(wrapper).vm.$emit('pagination-current-page-change', 2);
        await flushPromises();

        expect(router.replace).not.toHaveBeenCalled();
        expect(router.push).not.toHaveBeenCalled();
        expect(repository.search).toHaveBeenCalledTimes(2);
    });

    it('reloads when the administration language changes', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const contextStore = Shopware.Store.get('context');
        const previousLanguageId = contextStore.api.languageId;

        await createWrapper({
            repository,
        });

        contextStore.api.languageId = 'new-language-id';
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);

        contextStore.api.languageId = previousLanguageId;
    });

    it('does not reload on administration language changes when reloadOnLanguageChange is disabled', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const contextStore = Shopware.Store.get('context');
        const previousLanguageId = contextStore.api.languageId;

        try {
            await createWrapper({
                repository,
                reloadOnLanguageChange: false,
            });

            contextStore.api.languageId = 'new-language-id';
            await flushPromises();

            expect(repository.search).toHaveBeenCalledTimes(1);
        } finally {
            contextStore.api.languageId = previousLanguageId;
        }
    });
});
