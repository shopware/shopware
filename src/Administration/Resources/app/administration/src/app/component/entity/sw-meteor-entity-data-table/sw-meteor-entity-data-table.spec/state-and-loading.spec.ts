/**
 * @sw-package framework
 */

import {
    createDeferred,
    createRepositoryMock,
    createRepositoryMockWithSearch,
    createSearchResult,
    createWrapper,
    flushPromises,
    getLastSearchCriteria,
    getSearchMock,
    getSetupState,
    getTable,
    nextTick,
    records,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';
import type { EntityCollection, TestSearchMock } from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/state-and-loading', () => {
    registerSwMeteorEntityDataTableHooks();

    it('loads records on mount with the default context and emits load-success', async () => {
        const searchResult = createSearchResult(
            [
                {
                    id: 'record-3',
                    name: 'Third record',
                },
            ],
            37,
        );
        const repository = createRepositoryMock(searchResult);
        const wrapper = createWrapper({
            props: {
                repository,
                initialPage: 2,
                initialLimit: 10,
                initialSearchTerm: 'shirt',
            },
        });

        await flushPromises();

        const usedCriteria = getLastSearchCriteria(repository);

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(getSearchMock(repository)).toHaveBeenCalledWith(usedCriteria, Shopware.Context.api);
        expect(usedCriteria.parse()).toEqual(
            expect.objectContaining({
                page: 2,
                limit: 10,
                term: 'shirt',
            }),
        );
        expect(getTable(wrapper).props('dataSource')).toEqual(searchResult);
        expect(getTable(wrapper).props('paginationTotalItems')).toBe(37);
        expect(wrapper.emitted('load-success')).toEqual([
            [
                {
                    records: searchResult,
                    total: 37,
                    state: {
                        page: 2,
                        limit: 10,
                        searchTerm: 'shirt',
                    },
                },
            ],
        ]);
    });

    it('emits load-error and keeps previous records when loading fails', async () => {
        const searchResult = createSearchResult(records, 2);
        const repository = createRepositoryMock(searchResult);
        const wrapper = createWrapper({
            props: {
                repository,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        const error = new Error('Failed to load records');
        getSearchMock(repository).mockRejectedValueOnce(error);

        await wrapper.find('.mt-data-table-stub__reload').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(getTable(wrapper).props('dataSource')).toEqual(searchResult);
        expect(getTable(wrapper).props('paginationTotalItems')).toBe(2);
        expect(wrapper.emitted('load-error')).toEqual([
            [
                {
                    error,
                    state: {
                        page: 1,
                        limit: 25,
                        searchTerm: '',
                    },
                },
            ],
        ]);
    });

    it('sets loading while a request is pending', async () => {
        const deferred = createDeferred<EntityCollection<keyof EntitySchema.Entities>>();
        const search: TestSearchMock = jest.fn<ReturnType<TestSearchMock>, Parameters<TestSearchMock>>();
        search.mockReturnValue(deferred.promise);
        const repository = createRepositoryMockWithSearch(search);
        const wrapper = createWrapper({
            props: {
                repository,
            },
        });

        await nextTick();

        expect(getTable(wrapper).props('isLoading')).toBe(true);

        deferred.resolve(createSearchResult());
        await flushPromises();

        expect(getTable(wrapper).props('isLoading')).toBe(false);
    });

    it('ignores a stale load response when a newer load is in flight', async () => {
        const staleResult = createSearchResult(
            [
                {
                    id: 'stale-record',
                    name: 'Stale record',
                },
            ],
            1,
        );
        const freshResult = createSearchResult(
            [
                {
                    id: 'fresh-record',
                    name: 'Fresh record',
                },
            ],
            2,
        );

        const staleDeferred = createDeferred<EntityCollection<keyof EntitySchema.Entities>>();
        const freshDeferred = createDeferred<EntityCollection<keyof EntitySchema.Entities>>();
        const search: TestSearchMock = jest.fn<ReturnType<TestSearchMock>, Parameters<TestSearchMock>>();
        search
            .mockResolvedValueOnce(createSearchResult())
            .mockReturnValueOnce(staleDeferred.promise)
            .mockReturnValueOnce(freshDeferred.promise);
        const repository = createRepositoryMockWithSearch(search);
        const wrapper = createWrapper({
            props: {
                repository,
            },
        });

        await flushPromises();

        const setSearchTerm = getSetupState(wrapper).setSearchTerm as (term: string) => Promise<void>;

        void setSearchTerm('stale');
        void setSearchTerm('fresh');

        freshDeferred.resolve(freshResult);
        await flushPromises();
        staleDeferred.resolve(staleResult);
        await flushPromises();

        expect(getTable(wrapper).props('dataSource')).toEqual(freshResult);
        expect(getTable(wrapper).props('paginationTotalItems')).toBe(2);
        expect(getTable(wrapper).props('isLoading')).toBe(false);

        const loadSuccessEvents = wrapper.emitted('load-success') ?? [];
        const lastLoadSuccess = loadSuccessEvents[loadSuccessEvents.length - 1][0] as { records: unknown };

        expect(loadSuccessEvents).toHaveLength(2);
        expect(lastLoadSuccess.records).toEqual(freshResult);
    });

    it('page changes emit state-change and load once', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                initialPage: 2,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__page').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('state-change')).toEqual([
            [
                {
                    page: 3,
                    limit: 25,
                    searchTerm: '',
                },
            ],
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 3,
                limit: 25,
            }),
        );
    });

    it('limit changes reset the page, emit state-change, and load once', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                initialPage: 3,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__limit').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('state-change')).toEqual([
            [
                {
                    page: 1,
                    limit: 50,
                    searchTerm: '',
                },
            ],
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 1,
                limit: 50,
            }),
        );
    });

    it('search changes reset the page, emit state-change, and load once', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                initialPage: 3,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__search').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('state-change')).toEqual([
            [
                {
                    page: 1,
                    limit: 25,
                    searchTerm: 'needle',
                },
            ],
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 1,
                term: 'needle',
            }),
        );
    });

    it('sort changes reset the page, emit state-change, and load once', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                initialPage: 3,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__sort').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('state-change')).toEqual([
            [
                {
                    page: 1,
                    limit: 25,
                    searchTerm: '',
                    sort: {
                        property: 'name',
                        direction: 'DESC',
                    },
                },
            ],
        ]);
        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                page: 1,
                sort: [
                    {
                        field: 'name',
                        order: 'DESC',
                        naturalSorting: false,
                    },
                ],
            }),
        );
    });

    it('syncs changed initial state props without emitting a table state change', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                initialPage: 2,
                initialLimit: 10,
                initialSearchTerm: 'shirt',
                initialSort: {
                    property: 'name',
                    direction: 'ASC',
                },
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.setProps({
            initialPage: 4,
            initialLimit: 50,
            initialSearchTerm: 'jacket',
            initialSort: {
                property: 'name',
                direction: 'DESC',
            },
        });
        await nextTick();

        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                currentPage: 4,
                paginationLimit: 50,
                searchValue: 'jacket',
                sortBy: 'name',
                sortDirection: 'DESC',
            }),
        );
        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(wrapper.emitted('state-change')).toBeUndefined();
    });

    it('reloads without changing state', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__reload').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('state-change')).toBeUndefined();
    });
});
