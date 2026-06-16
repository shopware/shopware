/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package framework
 */

import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { h, nextTick, ref } from 'vue';
import type { SetupContext } from 'vue';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import SwMeteorEntityDataTable from './sw-meteor-entity-data-table.vue';
import type { SwMeteorEntityDataTableColumn, SwMeteorEntityDataTableState } from './sw-meteor-entity-data-table.types';

const componentName = 'sw-meteor-entity-data-table';
const { Criteria } = Shopware.Data;
const shopwareApplication = Shopware.Application as unknown as {
    view: {
        router?: TestRouter;
    };
};

type TestRepository = Repository<keyof EntitySchema.Entities>;

type TestColumn = SwMeteorEntityDataTableColumn;

type TestRecord = {
    id: string;
    name: string;
    [key: string]: unknown;
};

type TestSearchMock = jest.Mock<Promise<EntityCollection<keyof EntitySchema.Entities>>, [CriteriaType, ApiContext]>;

type TestProps = {
    repository: TestRepository;
    columns: TestColumn[];
    criteria?: CriteriaType | null;
    context?: ApiContext | null;
    initialPage?: number;
    initialLimit?: number;
    initialSearchTerm?: string;
    initialSort?: SwMeteorEntityDataTableState['sort'] | null;
    paginationOptions?: number[];
    searchable?: boolean;
    reloadable?: boolean;
    selectable?: boolean;
    detailRoute?: string;
};

type SlotRenderers = Record<string, string | (() => ReturnType<typeof h>)>;

type MtDataTableStubProps = {
    columns?: TestColumn[];
    dataSource?: TestRecord[];
};

type TestRouter = {
    push: jest.Mock;
};

const columns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
    },
];

const records: TestRecord[] = [
    {
        id: 'record-1',
        name: 'First record',
    },
    {
        id: 'record-2',
        name: 'Second record',
    },
];

const MtDataTableStub = {
    name: 'mt-data-table',
    emits: [
        'sort-change',
        'pagination-current-page-change',
        'pagination-limit-change',
        'search-value-change',
        'selection-change',
        'multiple-selection-change',
        'reload',
        'open-details',
    ],
    props: [
        'dataSource',
        'columns',
        'currentPage',
        'paginationLimit',
        'paginationOptions',
        'paginationTotalItems',
        'sortBy',
        'sortDirection',
        'searchValue',
        'isLoading',
        'allowRowSelection',
        'selectedRows',
        'disableSearch',
        'enableReload',
        'disableEdit',
        'disableDelete',
        'disableSettingsTable',
    ],
    setup(rawProps: Record<string, unknown>, setupContext: SetupContext) {
        const props = rawProps as MtDataTableStubProps;
        const { emit, slots } = setupContext;
        const getCurrentRecords = () => {
            return props.dataSource && props.dataSource.length > 0 ? props.dataSource : records;
        };

        return () =>
            h('div', { class: 'mt-data-table-stub' }, [
                slots.toolbar ? h('div', { class: 'mt-data-table-stub__toolbar' }, slots.toolbar()) : null,
                slots['empty-state'] ? h('div', { class: 'mt-data-table-stub__empty-state' }, slots['empty-state']()) : null,
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__sort',
                        type: 'button',
                        onClick: () => emit('sort-change', props.columns?.[0]?.property ?? 'name', 'DESC'),
                    },
                    'Sort',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__page',
                        type: 'button',
                        onClick: () => emit('pagination-current-page-change', 3),
                    },
                    'Page',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__limit',
                        type: 'button',
                        onClick: () => emit('pagination-limit-change', 50),
                    },
                    'Limit',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__search',
                        type: 'button',
                        onClick: () => emit('search-value-change', 'needle'),
                    },
                    'Search',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__select-row',
                        type: 'button',
                        onClick: () => emit('selection-change', { id: 'record-1', value: true }),
                    },
                    'Select row',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__deselect-row',
                        type: 'button',
                        onClick: () => emit('selection-change', { id: 'record-1', value: false }),
                    },
                    'Deselect row',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__select-all',
                        type: 'button',
                        onClick: () =>
                            emit('multiple-selection-change', {
                                selections: [
                                    'record-1',
                                    'record-2',
                                ],
                                value: true,
                            }),
                    },
                    'Select all',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__deselect-all',
                        type: 'button',
                        onClick: () =>
                            emit('multiple-selection-change', {
                                selections: [
                                    'record-1',
                                    'record-2',
                                ],
                                value: false,
                            }),
                    },
                    'Deselect all',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__reload',
                        type: 'button',
                        onClick: () => emit('reload'),
                    },
                    'Reload',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__details',
                        type: 'button',
                        onClick: () => emit('open-details', getCurrentRecords()[0]),
                    },
                    'Details',
                ),
            ]);
    },
};

function createSearchResult(
    resultRecords: TestRecord[] = records,
    total = resultRecords.length,
): EntityCollection<keyof EntitySchema.Entities> {
    return Object.assign([...resultRecords], { total }) as unknown as EntityCollection<keyof EntitySchema.Entities>;
}

function createRepositoryMock(searchResult = createSearchResult()): TestRepository {
    const search: TestSearchMock = jest.fn<ReturnType<TestSearchMock>, Parameters<TestSearchMock>>();
    search.mockResolvedValue(searchResult);

    return {
        search,
    } as unknown as TestRepository;
}

function createRepositoryMockWithSearch(search: TestSearchMock): TestRepository {
    return {
        search,
    } as unknown as TestRepository;
}

function getSearchMock(repository: TestRepository): TestSearchMock {
    return (repository as unknown as { search: TestSearchMock }).search;
}

function createWrapper(
    options: {
        props?: Partial<TestProps>;
        slots?: SlotRenderers;
        router?: TestRouter;
    } = {},
) {
    return mount(SwMeteorEntityDataTable, {
        props: {
            repository: createRepositoryMock(),
            columns,
            ...options.props,
        },
        slots: options.slots,
        global: {
            mocks: options.router
                ? {
                      $router: options.router,
                  }
                : {},
            stubs: {
                'mt-data-table': MtDataTableStub,
            },
        },
    });
}

function getTable(wrapper: VueWrapper) {
    return wrapper.findComponent(MtDataTableStub);
}

function getLastSearchCriteria(repository: TestRepository): CriteriaType {
    const searchMock = getSearchMock(repository);
    const lastCall = searchMock.mock.calls[searchMock.mock.calls.length - 1];

    expect(lastCall).toBeDefined();

    return lastCall[0];
}

function getSetupState(wrapper: VueWrapper): Record<string, unknown> {
    return (wrapper.vm.$ as unknown as { setupState: Record<string, unknown> }).setupState;
}

function createDeferred<TValue>() {
    let resolve: (value: TValue) => void = () => {};
    let reject: (reason?: unknown) => void = () => {};
    const promise = new Promise<TValue>((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return {
        promise,
        resolve,
        reject,
    };
}

describe('src/app/component/entity/sw-meteor-entity-data-table', () => {
    const originalRouter = shopwareApplication.view.router;

    beforeEach(() => {
        delete _overridesMap[componentName];
    });

    afterEach(() => {
        shopwareApplication.view.router = originalRouter;
    });

    it('declares only the new wrapper props and emits', () => {
        const componentOptions = SwMeteorEntityDataTable as unknown as {
            props: Record<string, unknown>;
            emits: string[];
        };

        expect(Object.keys(componentOptions.props)).toEqual(
            expect.arrayContaining([
                'repository',
                'columns',
                'criteria',
                'context',
                'initialPage',
                'initialLimit',
                'initialSearchTerm',
                'initialSort',
                'paginationOptions',
                'searchable',
                'reloadable',
                'selectable',
                'detailRoute',
            ]),
        );
        expect(Object.keys(componentOptions.props)).not.toEqual(
            expect.arrayContaining([
                'records',
                'total',
                'isLoading',
                'disableDataFetching',
                'allowEdit',
                'allowView',
                'allowDelete',
                'allowBulkDelete',
                'allowBulkEdit',
                'showActions',
                'showSettings',
                'additionalContextButtons',
                'columnChanges',
            ]),
        );
        expect(componentOptions.emits).toEqual([
            'state-change',
            'selection-change',
            'load-success',
            'load-error',
            'open-detail',
        ]);
    });

    it('renders mt-data-table and disables unsupported table behavior', () => {
        const wrapper = createWrapper();
        const table = getTable(wrapper);

        expect(table.exists()).toBe(true);
        expect(table.props()).toEqual(
            expect.objectContaining({
                currentPage: 1,
                paginationLimit: 25,
                paginationOptions: [
                    5,
                    10,
                    25,
                    50,
                ],
                sortBy: '',
                sortDirection: 'ASC',
                searchValue: '',
                allowRowSelection: false,
                selectedRows: [],
                disableSearch: false,
                enableReload: false,
                disableEdit: true,
                disableDelete: true,
                disableSettingsTable: true,
            }),
        );
    });

    it('resolves columns by declaration order and keeps renderer options while stripping sorting metadata', () => {
        const renderItemBadge = () => ({
            label: 'Active',
            variant: 'positive' as const,
        });
        const wrapper = createWrapper({
            props: {
                columns: [
                    {
                        label: 'Name',
                        property: 'name',
                    },
                    {
                        label: 'Customer name',
                        property: 'customerName',
                        renderer: 'badge',
                        rendererOptions: {
                            renderItemBadge,
                        },
                        sortField: [
                            'firstName',
                            'lastName',
                        ],
                        naturalSorting: true,
                        width: 180,
                    },
                ],
            },
        });
        const tableColumns = getTable(wrapper).props('columns') as Record<string, unknown>[];

        expect(tableColumns).toEqual([
            {
                label: 'Name',
                property: 'name',
                renderer: 'text',
                position: 0,
            },
            {
                label: 'Customer name',
                property: 'customerName',
                renderer: 'badge',
                rendererOptions: {
                    renderItemBadge,
                },
                position: 100,
                width: 180,
            },
        ]);
        expect(tableColumns[1]).not.toHaveProperty('sortField');
        expect(tableColumns[1]).not.toHaveProperty('naturalSorting');
    });

    it('enforces renderer-specific column options at the type level', () => {
        const badgeColumn: SwMeteorEntityDataTableColumn = {
            label: 'Status',
            property: 'status',
            renderer: 'badge',
            rendererOptions: {
                renderItemBadge: () => ({
                    label: 'Active',
                    variant: 'positive' as const,
                }),
            },
        };

        // @ts-expect-error - badge columns must declare rendererOptions
        const badgeColumnWithoutOptions: SwMeteorEntityDataTableColumn = {
            label: 'Status',
            property: 'status',
            renderer: 'badge',
        };

        // @ts-expect-error - price columns must declare rendererOptions
        const priceColumnWithoutOptions: SwMeteorEntityDataTableColumn = {
            label: 'Price',
            property: 'price',
            renderer: 'price',
        };

        expect(badgeColumn).toBeDefined();
        expect(badgeColumnWithoutOptions).toBeDefined();
        expect(priceColumnWithoutOptions).toBeDefined();
    });

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

    it('clones provided criteria before applying state and uses an explicit context', async () => {
        const repository = createRepositoryMock();
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        } as ApiContext;
        const criteria = new Criteria(9, 99);
        criteria.addFilter(Criteria.equals('active', true));
        criteria.addPostFilter(Criteria.equals('visible', true));
        criteria.addAssociation('manufacturer');
        criteria.getAssociation('manufacturer').addFilter(Criteria.equals('name', 'ACME'));
        criteria.addAggregation(Criteria.count('count-id', 'id'));
        criteria.addIncludes({
            product: [
                'id',
                'name',
            ],
        });
        criteria.addFields('id', 'name');
        criteria.addGrouping('manufacturerId');
        criteria.addGroupField('manufacturerId');
        criteria.setTotalCountMode(2);
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

        const originalCriteriaPayload = criteria.parse();
        createWrapper({
            props: {
                repository,
                criteria,
                context,
                initialPage: 2,
                initialLimit: 10,
                initialSearchTerm: 'shirt',
            },
        });

        await flushPromises();

        const usedCriteria = getLastSearchCriteria(repository);
        const usedCriteriaPayload = usedCriteria.parse();

        expect(usedCriteria).not.toBe(criteria);
        expect(criteria.parse()).toEqual(originalCriteriaPayload);
        expect(usedCriteriaPayload).toEqual(
            expect.objectContaining({
                page: 2,
                limit: 10,
                term: 'shirt',
                filter: originalCriteriaPayload.filter,
                'post-filter': originalCriteriaPayload['post-filter'],
                aggregations: originalCriteriaPayload.aggregations,
                includes: originalCriteriaPayload.includes,
                fields: originalCriteriaPayload.fields,
                grouping: originalCriteriaPayload.grouping,
                groupFields: originalCriteriaPayload.groupFields,
                associations: originalCriteriaPayload.associations,
                'total-count-mode': 2,
            }),
        );
        expect(usedCriteriaPayload.sort).toBeUndefined();
        expect(getSearchMock(repository)).toHaveBeenCalledWith(usedCriteria, context);
    });

    it('applies initial sorting by property', async () => {
        const repository = createRepositoryMock();

        createWrapper({
            props: {
                repository,
                initialSort: {
                    property: 'name',
                    direction: 'DESC',
                },
            },
        });

        await flushPromises();

        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
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

    it('applies sorting by sortField', async () => {
        const repository = createRepositoryMock();

        createWrapper({
            props: {
                repository,
                columns: [
                    {
                        label: 'Customer name',
                        property: 'customerName',
                        sortField: 'customer.lastName',
                    },
                ],
                initialSort: {
                    property: 'customerName',
                    direction: 'ASC',
                },
            },
        });

        await flushPromises();

        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                sort: [
                    {
                        field: 'customer.lastName',
                        order: 'ASC',
                        naturalSorting: false,
                    },
                ],
            }),
        );
    });

    it('applies multiple sortField values with natural sorting', async () => {
        const repository = createRepositoryMock();

        createWrapper({
            props: {
                repository,
                columns: [
                    {
                        label: 'Customer name',
                        property: 'customerName',
                        sortField: [
                            'firstName',
                            'lastName',
                        ],
                        naturalSorting: true,
                    },
                ],
                initialSort: {
                    property: 'customerName',
                    direction: 'DESC',
                },
            },
        });

        await flushPromises();

        expect(getLastSearchCriteria(repository).parse()).toEqual(
            expect.objectContaining({
                sort: [
                    {
                        field: 'firstName',
                        order: 'DESC',
                        naturalSorting: true,
                    },
                    {
                        field: 'lastName',
                        order: 'DESC',
                        naturalSorting: true,
                    },
                ],
            }),
        );
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

    it('adds and removes a single row selection and emits the complete selected ID list', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
            },
        });

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual(['record-1']);

        await wrapper.find('.mt-data-table-stub__deselect-row').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                ['record-1'],
            ],
            [
                [],
            ],
        ]);
    });

    it('merges and clears bulk selections without duplicating ids', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
            },
        });

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([
            'record-1',
            'record-2',
        ]);

        await wrapper.find('.mt-data-table-stub__deselect-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                ['record-1'],
            ],
            [
                [
                    'record-1',
                    'record-2',
                ],
            ],
            [
                [],
            ],
        ]);
    });

    it('emits open-detail without routing when detailRoute is not configured', async () => {
        const router = {
            push: jest.fn(),
        };
        const wrapper = createWrapper({
            router,
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__details').trigger('click');

        expect(router.push).not.toHaveBeenCalled();
        expect(wrapper.emitted('open-detail')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                },
            ],
        ]);
    });

    it('routes to detailRoute when configured', async () => {
        const router = {
            push: jest.fn(),
        };
        shopwareApplication.view.router = router;
        const wrapper = createWrapper({
            props: {
                detailRoute: 'sw.product.detail',
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__details').trigger('click');

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.product.detail',
            params: {
                id: 'record-1',
            },
        });
        expect(wrapper.emitted('open-detail')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                },
            ],
        ]);
    });

    it('forwards toolbar and empty-state slots only when present', () => {
        const wrapperWithoutSlots = createWrapper();

        expect(wrapperWithoutSlots.find('.mt-data-table-stub__toolbar').exists()).toBe(false);
        expect(wrapperWithoutSlots.find('.mt-data-table-stub__empty-state').exists()).toBe(false);

        const wrapperWithSlots = createWrapper({
            slots: {
                toolbar: '<span class="toolbar-slot">Toolbar</span>',
                'empty-state': '<span class="empty-state-slot">Empty</span>',
            },
        });

        expect(wrapperWithSlots.find('.toolbar-slot').text()).toBe('Toolbar');
        expect(wrapperWithSlots.find('.empty-state-slot').text()).toBe('Empty');
    });

    it('exposes the new public setup API without legacy placeholders', () => {
        const wrapper = createWrapper();
        const setupStateKeys = Object.keys(getSetupState(wrapper));

        expect(setupStateKeys).toEqual(
            expect.arrayContaining([
                'records',
                'total',
                'loading',
                'state',
                'selectedIds',
                'resolvedColumns',
                'buildCriteria',
                'load',
                'reload',
                'setPage',
                'setLimit',
                'setSearchTerm',
                'setSort',
                'setSelectedIds',
            ]),
        );
        expect(setupStateKeys).not.toEqual(
            expect.arrayContaining([
                'dataSource',
                'totalItems',
                'page',
                'limit',
                'sortBy',
                'sortDirection',
                'searchTerm',
                'columnChanges',
                'normalizedColumns',
                'deleteItem',
                'deleteItems',
            ]),
        );
    });

    it('allows overrideComponentSetup to override public setup state', async () => {
        const overrideRecords = [
            {
                id: 'override-record',
                name: 'Override record',
            },
        ];

        overrideComponentSetup<typeof SwMeteorEntityDataTable>()(componentName, () => ({
            records: ref(overrideRecords),
            total: ref(1),
            state: ref({
                page: 7,
                limit: 100,
                searchTerm: 'override',
            }),
        }));

        const wrapper = createWrapper();

        await nextTick();

        const table = getTable(wrapper);

        expect(table.props('dataSource')).toEqual(overrideRecords);
        expect(table.props('paginationTotalItems')).toBe(1);
        expect(table.props('currentPage')).toBe(7);
        expect(table.props('paginationLimit')).toBe(100);
        expect(table.props('searchValue')).toBe('override');
    });
});
