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
import { isNativeShopwareComponentName } from 'src/app/component/native-shopware-components';
import SwMeteorEntityDataTable from './sw-meteor-entity-data-table.vue';
import type {
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableCriteriaResolver,
    SwMeteorEntityDataTableCriteriaResolverPayload,
    SwMeteorEntityDataTableLayout,
    SwMeteorEntityDataTableState,
} from './sw-meteor-entity-data-table.types';

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
type TestDeleteMock = jest.Mock<Promise<unknown>, [string, ApiContext]>;
type TestSyncDeletedMock = jest.Mock<Promise<void>, [string[], ApiContext]>;
type TestSaveMock = jest.Mock<Promise<void>, [TestRecord, ApiContext]>;

type TestProps = {
    repository: TestRepository;
    columns: TestColumn[];
    identifier?: string;
    criteria?: CriteriaType | null;
    criteriaResolver?: SwMeteorEntityDataTableCriteriaResolver | null;
    context?: ApiContext | null;
    initialPage?: number;
    initialLimit?: number;
    initialSearchTerm?: string;
    initialSort?: SwMeteorEntityDataTableState['sort'] | null;
    paginationOptions?: number[];
    layout?: SwMeteorEntityDataTableLayout;
    searchable?: boolean;
    reloadable?: boolean;
    selectable?: boolean;
    detailRoute?: string;
    allowEdit?: boolean;
    allowInlineEdit?: boolean;
    allowDelete?: boolean;
    hideTableSettings?: boolean;
    additionalContextButtons?: Array<{
        key: string;
        label: string;
        type?: 'default' | 'active' | 'critical';
    }>;
};

type SlotRenderer = string | ((slotProps: Record<string, unknown>) => ReturnType<typeof h>);
type SlotRenderers = Record<string, SlotRenderer>;

type MtDataTableStubProps = {
    columns?: TestColumn[];
    dataSource?: TestRecord[];
    columnChanges?: Record<
        string,
        {
            position?: number;
            width?: number;
            visible?: boolean;
        }
    >;
};

type TestRouter = {
    push: jest.Mock;
};

type TestUserConfigEntity = {
    id?: string;
    key?: string;
    userId?: string;
    value?: unknown;
};

type TestUserConfigRepository = {
    search: jest.Mock<Promise<TestUserConfigEntity[]>, [CriteriaType, ApiContext]>;
    save: jest.Mock<Promise<void>, [TestUserConfigEntity, ApiContext]>;
    create: jest.Mock<TestUserConfigEntity, [ApiContext]>;
    getStoredEntity: () => TestUserConfigEntity | null;
};

const columns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
    },
];

const inlineEditColumns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
        inlineEdit: 'string',
    },
];

const persistedSettingsColumns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
    },
    {
        label: 'Link',
        property: 'link',
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
        'bulk-delete',
        'item-delete',
        'context-select',
        'change-show-outlines',
        'change-show-stripes',
        'change-outline-framing',
        'change-enable-row-numbering',
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
        'layout',
        'allowRowSelection',
        'allowBulkDelete',
        'selectedRows',
        'disableSearch',
        'enableReload',
        'disableEdit',
        'disableDelete',
        'disableSettingsTable',
        'columnChanges',
        'showOutlines',
        'showStripes',
        'enableOutlineFraming',
        'enableRowNumbering',
        'additionalContextButtons',
    ],
    setup(rawProps: Record<string, unknown>, setupContext: SetupContext) {
        const props = rawProps as MtDataTableStubProps;
        const { emit, slots } = setupContext;
        const getCurrentRecords = () => {
            return props.dataSource && props.dataSource.length > 0 ? props.dataSource : records;
        };
        const getNameColumn = () => {
            return props.columns?.find((column) => column.property === 'name') ?? columns[0];
        };

        return () =>
            h('div', { class: 'mt-data-table-stub' }, [
                slots.toolbar ? h('div', { class: 'mt-data-table-stub__toolbar' }, slots.toolbar()) : null,
                slots['empty-state'] ? h('div', { class: 'mt-data-table-stub__empty-state' }, slots['empty-state']()) : null,
                slots['column-name']
                    ? h(
                          'div',
                          { class: 'mt-data-table-stub__column-name' },
                          slots['column-name']({
                              data: getCurrentRecords()[0],
                              columnDefinition: getNameColumn(),
                          }),
                      )
                    : null,
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
                        class: 'mt-data-table-stub__change-column-settings',
                        type: 'button',
                        onClick: () => {
                            if (!props.columnChanges) {
                                return;
                            }

                            props.columnChanges.link = {
                                position: 0,
                                width: 320,
                                visible: true,
                            };
                            props.columnChanges.name = {
                                position: 100,
                                width: 280,
                                visible: false,
                            };
                        },
                    },
                    'Change column settings',
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
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__bulk-delete',
                        type: 'button',
                        onClick: () => emit('bulk-delete'),
                    },
                    'Bulk delete',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__delete',
                        type: 'button',
                        onClick: () => emit('item-delete', getCurrentRecords()[0]),
                    },
                    'Delete',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__context-select',
                        type: 'button',
                        onClick: () =>
                            emit('context-select', {
                                key: 'set-price',
                                data: getCurrentRecords()[0],
                            }),
                    },
                    'Context select',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__show-outlines',
                        type: 'button',
                        onClick: () => emit('change-show-outlines', false),
                    },
                    'Show outlines',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__show-stripes',
                        type: 'button',
                        onClick: () => emit('change-show-stripes', false),
                    },
                    'Show stripes',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__outline-framing',
                        type: 'button',
                        onClick: () => emit('change-outline-framing', true),
                    },
                    'Outline framing',
                ),
                h(
                    'button',
                    {
                        class: 'mt-data-table-stub__row-numbering',
                        type: 'button',
                        onClick: () => emit('change-enable-row-numbering', true),
                    },
                    'Row numbering',
                ),
            ]);
    },
};

const globalStubs = {
    'mt-data-table': MtDataTableStub,
    'sw-modal': {
        template: `
            <div class="sw-modal">
                <slot></slot>
                <slot name="modal-footer"></slot>
                <button
                    class="sw-modal__close"
                    type="button"
                    @click="$emit('modal-close')"
                >
                    Close
                </button>
            </div>
        `,
        emits: ['modal-close'],
    },
    'mt-button': {
        props: [
            'isLoading',
        ],
        template: `
            <button
                class="mt-button"
                type="button"
                :data-loading="isLoading"
                @click="$emit('click')"
            >
                <slot></slot>
            </button>
        `,
        emits: ['click'],
    },
    'mt-icon': true,
    'sw-data-grid-inline-edit': {
        props: [
            'value',
            'column',
            'compact',
        ],
        template: `
            <input
                class="sw-data-grid-inline-edit-stub"
                :value="value"
                @input="$emit('update:value', $event.target.value)"
            />
        `,
        emits: ['update:value'],
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
    const deleteMock: TestDeleteMock = jest.fn<ReturnType<TestDeleteMock>, Parameters<TestDeleteMock>>();
    const syncDeletedMock: TestSyncDeletedMock = jest.fn<ReturnType<TestSyncDeletedMock>, Parameters<TestSyncDeletedMock>>();
    const saveMock: TestSaveMock = jest.fn<ReturnType<TestSaveMock>, Parameters<TestSaveMock>>();
    search.mockResolvedValue(searchResult);
    deleteMock.mockResolvedValue({});
    syncDeletedMock.mockResolvedValue();
    saveMock.mockResolvedValue();

    return {
        search,
        delete: deleteMock,
        syncDeleted: syncDeletedMock,
        save: saveMock,
    } as unknown as TestRepository;
}

function createRepositoryMockWithSearch(search: TestSearchMock): TestRepository {
    const deleteMock: TestDeleteMock = jest.fn<ReturnType<TestDeleteMock>, Parameters<TestDeleteMock>>();
    const syncDeletedMock: TestSyncDeletedMock = jest.fn<ReturnType<TestSyncDeletedMock>, Parameters<TestSyncDeletedMock>>();
    const saveMock: TestSaveMock = jest.fn<ReturnType<TestSaveMock>, Parameters<TestSaveMock>>();
    deleteMock.mockResolvedValue({});
    syncDeletedMock.mockResolvedValue();
    saveMock.mockResolvedValue();

    return {
        search,
        delete: deleteMock,
        syncDeleted: syncDeletedMock,
        save: saveMock,
    } as unknown as TestRepository;
}

function createUserConfigRepositoryMock(initialEntity: TestUserConfigEntity | null = null): TestUserConfigRepository {
    let storedEntity = initialEntity;
    const search = jest.fn<Promise<TestUserConfigEntity[]>, [CriteriaType, ApiContext]>(() =>
        Promise.resolve(storedEntity ? [storedEntity] : []),
    );
    const save = jest.fn<Promise<void>, [TestUserConfigEntity, ApiContext]>((entity) => {
        storedEntity = entity;

        return Promise.resolve();
    });
    const create = jest.fn<TestUserConfigEntity, [ApiContext]>(() => ({
        id: 'created-user-config-id',
    }));

    return {
        search,
        save,
        create,
        getStoredEntity: () => storedEntity,
    };
}

function mockUserConfigRepository(userConfigRepository: TestUserConfigRepository): void {
    const aclService = Shopware.Service('acl') as {
        can: (privilege: string) => boolean;
    };
    const repositoryFactory = Shopware.Service('repositoryFactory') as {
        create: (entityName: keyof EntitySchema.Entities) => Repository<keyof EntitySchema.Entities>;
    };

    jest.spyOn(aclService, 'can').mockReturnValue(true);
    jest.spyOn(repositoryFactory, 'create').mockImplementation((entityName) => {
        if (entityName === 'user_config') {
            return userConfigRepository as unknown as Repository<keyof EntitySchema.Entities>;
        }

        return createRepositoryMock() as unknown as Repository<keyof EntitySchema.Entities>;
    });
}

function setCurrentUserWithUserConfigPrivileges(): void {
    Shopware.Store.get('session').setCurrentUser({
        id: 'current-user-id',
        admin: true,
        aclRoles: [
            {
                privileges: [
                    'user_config:read',
                    'user_config:create',
                    'user_config:update',
                ],
            },
        ],
    } as EntitySchema.user);
}

function getSearchMock(repository: TestRepository): TestSearchMock {
    return (repository as unknown as { search: TestSearchMock }).search;
}

function getDeleteMock(repository: TestRepository): TestDeleteMock {
    return (repository as unknown as { delete: TestDeleteMock }).delete;
}

function getSyncDeletedMock(repository: TestRepository): TestSyncDeletedMock {
    return (repository as unknown as { syncDeleted: TestSyncDeletedMock }).syncDeleted;
}

function getSaveMock(repository: TestRepository): TestSaveMock {
    return (repository as unknown as { save: TestSaveMock }).save;
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
            stubs: globalStubs,
        },
    });
}

function createListingConsumerWrapper(repository = createRepositoryMock()) {
    return mount(
        {
            name: 'sw-meteor-entity-data-table-listing-consumer',
            components: {
                SwMeteorEntityDataTable,
            },
            mixins: [
                Shopware.Mixin.getByName('listing'),
            ],
            template: `
                <sw-meteor-entity-data-table
                    :repository="repository"
                    :columns="columns"
                    selectable
                    @selection-change="updateSelection"
                />
            `,
            data() {
                return {
                    repository,
                    columns,
                    disableRouteParams: true,
                };
            },
            methods: {
                getList: jest.fn(),
            },
        },
        {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.product.index',
                        query: {},
                        params: {},
                    },
                    $router: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                },
                provide: {
                    searchRankingService: {
                        isValidTerm: (term?: string) => {
                            return !!term && term.trim().length >= 1;
                        },
                        getSearchFieldsByEntity: jest.fn(),
                        buildSearchQueriesForEntity: jest.fn(),
                    },
                    feature: {},
                },
                stubs: globalStubs,
            },
        },
    );
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
    const originalCurrentUser = Shopware.Store.get('session').currentUser;

    beforeEach(() => {
        delete _overridesMap[componentName];
        Shopware.Component.getOverrideRegistry().delete(componentName);
    });

    afterEach(() => {
        shopwareApplication.view.router = originalRouter;
        Shopware.Store.get('shopwareApps').selectedIds = [];
        Shopware.Store.get('swBulkEdit').selectedIds = [];

        if (originalCurrentUser) {
            Shopware.Store.get('session').setCurrentUser(originalCurrentUser);
        } else {
            Shopware.Store.get('session').removeCurrentUser();
        }

        jest.restoreAllMocks();
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
                'identifier',
                'criteria',
                'context',
                'initialPage',
                'initialLimit',
                'initialSearchTerm',
                'initialSort',
                'paginationOptions',
                'layout',
                'searchable',
                'reloadable',
                'selectable',
                'detailRoute',
                'allowEdit',
                'allowInlineEdit',
                'allowDelete',
                'hideTableSettings',
                'additionalContextButtons',
            ]),
        );
        expect(Object.keys(componentOptions.props)).not.toEqual(
            expect.arrayContaining([
                'records',
                'total',
                'isLoading',
                'disableDataFetching',
                'allowView',
                'allowBulkDelete',
                'allowBulkEdit',
                'showActions',
                'showSettings',
                'columnChanges',
            ]),
        );
        expect(componentOptions.emits).toEqual([
            'state-change',
            'selection-change',
            'selected-ids-change',
            'load-success',
            'load-error',
            'open-detail',
            'delete-finish',
            'delete-failed',
            'bulk-delete-finish',
            'bulk-delete-failed',
            'context-select',
            'inline-edit-save',
            'inline-edit-cancel',
        ]);
    });

    it('renders mt-data-table and enables table settings by default', () => {
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
                layout: 'default',
                allowRowSelection: false,
                allowBulkDelete: false,
                selectedRows: [],
                disableSearch: false,
                enableReload: false,
                disableEdit: true,
                disableDelete: true,
                disableSettingsTable: false,
                columnChanges: {},
                showOutlines: true,
                showStripes: true,
                enableOutlineFraming: false,
                enableRowNumbering: false,
                additionalContextButtons: [],
            }),
        );
    });

    it('allows table settings to be hidden', () => {
        const wrapper = createWrapper({
            props: {
                hideTableSettings: true,
            },
        });

        expect(getTable(wrapper).props('disableSettingsTable')).toBe(true);
    });

    it('forwards opt-in row action props to mt-data-table', () => {
        const additionalContextButtons = [
            {
                key: 'set-price',
                label: 'Set price',
                type: 'active' as const,
            },
        ];
        const wrapper = createWrapper({
            props: {
                allowEdit: true,
                allowDelete: true,
                additionalContextButtons,
            },
        });

        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                disableEdit: false,
                disableDelete: false,
                disableSettingsTable: false,
                additionalContextButtons,
            }),
        );
    });

    it('updates in-session table setting props from mt-data-table events', async () => {
        const wrapper = createWrapper();

        await wrapper.find('.mt-data-table-stub__show-outlines').trigger('click');
        await wrapper.find('.mt-data-table-stub__show-stripes').trigger('click');
        await wrapper.find('.mt-data-table-stub__outline-framing').trigger('click');
        await wrapper.find('.mt-data-table-stub__row-numbering').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                showOutlines: false,
                showStripes: false,
                enableOutlineFraming: true,
                enableRowNumbering: true,
            }),
        );
    });

    it('loads table settings from user_config when an identifier is configured', async () => {
        setCurrentUserWithUserConfigPrivileges();

        const userConfigRepository = createUserConfigRepositoryMock({
            id: 'user-config-id',
            key: 'grid.setting.sw-manufacturer-list',
            userId: 'current-user-id',
            value: {
                columns: [
                    {
                        dataIndex: 'link',
                        width: 320,
                        visible: true,
                    },
                    {
                        dataIndex: 'name',
                        width: 280,
                        visible: false,
                    },
                    {
                        dataIndex: 'removed-column',
                        width: 120,
                        visible: false,
                    },
                ],
                showOutlines: false,
                showStripes: false,
                enableOutlineFraming: true,
                enableRowNumbering: true,
            },
        });

        mockUserConfigRepository(userConfigRepository);

        const wrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        await nextTick();

        expect(userConfigRepository.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'grid.setting.sw-manufacturer-list',
                    },
                    {
                        field: 'userId',
                        type: 'equals',
                        value: 'current-user-id',
                    },
                ],
            }),
            Shopware.Context.api,
        );
        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                columnChanges: {
                    link: {
                        position: 0,
                        width: 320,
                        visible: true,
                    },
                    name: {
                        position: 100,
                        width: 280,
                        visible: false,
                    },
                },
                showOutlines: false,
                showStripes: false,
                enableOutlineFraming: true,
                enableRowNumbering: true,
            }),
        );
        expect(userConfigRepository.save).not.toHaveBeenCalled();
    });

    it('persists table settings to user_config and restores them after remounting', async () => {
        setCurrentUserWithUserConfigPrivileges();

        const userConfigRepository = createUserConfigRepositoryMock();
        mockUserConfigRepository(userConfigRepository);

        const wrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        userConfigRepository.save.mockClear();

        await wrapper.find('.mt-data-table-stub__change-column-settings').trigger('click');
        await wrapper.find('.mt-data-table-stub__show-outlines').trigger('click');
        await wrapper.find('.mt-data-table-stub__row-numbering').trigger('click');
        await flushPromises();
        await nextTick();

        expect(userConfigRepository.create).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.save).toHaveBeenCalled();
        expect(userConfigRepository.getStoredEntity()).toEqual(
            expect.objectContaining({
                id: 'created-user-config-id',
                key: 'grid.setting.sw-manufacturer-list',
                userId: 'current-user-id',
                value: {
                    columns: [
                        {
                            property: 'link',
                            dataIndex: 'link',
                            position: 0,
                            width: 320,
                            visible: true,
                        },
                        {
                            property: 'name',
                            dataIndex: 'name',
                            position: 100,
                            width: 280,
                            visible: false,
                        },
                    ],
                    showOutlines: false,
                    showStripes: true,
                    enableOutlineFraming: false,
                    enableRowNumbering: true,
                },
            }),
        );

        wrapper.unmount();
        userConfigRepository.save.mockClear();

        const remountedWrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        await nextTick();

        expect(getTable(remountedWrapper).props()).toEqual(
            expect.objectContaining({
                columnChanges: {
                    link: {
                        position: 0,
                        width: 320,
                        visible: true,
                    },
                    name: {
                        position: 100,
                        width: 280,
                        visible: false,
                    },
                },
                showOutlines: false,
                showStripes: true,
                enableOutlineFraming: false,
                enableRowNumbering: true,
            }),
        );
        expect(userConfigRepository.save).not.toHaveBeenCalled();
    });

    it('allows Meteor bulk delete only when row selection and deletion are enabled', () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
                allowDelete: true,
            },
        });

        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                allowRowSelection: true,
                allowBulkDelete: true,
                disableDelete: false,
            }),
        );
    });

    it('forwards the full layout option to mt-data-table', () => {
        const wrapper = createWrapper({
            props: {
                layout: 'full',
            },
        });

        expect(getTable(wrapper).props('layout')).toBe('full');
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
                        inlineEdit: 'string',
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
                inlineEdit: 'string',
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

    it('does not enter inline edit mode when inline editing is disabled', async () => {
        const wrapper = createWrapper({
            props: {
                columns: inlineEditColumns,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await nextTick();

        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(false);
    });

    it('enters inline edit mode for editable columns on double click', async () => {
        const wrapper = createWrapper({
            props: {
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await nextTick();

        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-meteor-entity-data-table__inline-edit-save').exists()).toBe(true);
        expect(wrapper.find('.sw-meteor-entity-data-table__inline-edit-cancel').exists()).toBe(true);
    });

    it('saves inline edited records and reloads the table', async () => {
        const editableRecords = [
            {
                id: 'record-1',
                name: 'First record',
            },
        ];
        const repository = createRepositoryMock(createSearchResult(editableRecords));
        const wrapper = createWrapper({
            props: {
                repository,
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await wrapper.find('.sw-data-grid-inline-edit-stub').setValue('Updated record');
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-save').trigger('click');

        const saveMock = getSaveMock(repository);
        const emittedSave = wrapper.emitted('inline-edit-save');

        expect(saveMock).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'record-1',
                name: 'Updated record',
            }),
            Shopware.Context.api,
        );
        expect(emittedSave?.[0][0]).toBeInstanceOf(Promise);
        expect(emittedSave?.[0][1]).toEqual(
            expect.objectContaining({
                id: 'record-1',
                name: 'Updated record',
            }),
        );

        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(false);
    });

    it('keeps inline edit mode active when saving fails', async () => {
        const editableRecords = [
            {
                id: 'record-1',
                name: 'First record',
            },
        ];
        const repository = createRepositoryMock(createSearchResult(editableRecords));
        const saveError = new Error('Could not save');

        getSaveMock(repository).mockRejectedValueOnce(saveError);

        const wrapper = createWrapper({
            props: {
                repository,
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await wrapper.find('.sw-data-grid-inline-edit-stub').setValue('Updated record');
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-save').trigger('click');

        const emittedSave = wrapper.emitted('inline-edit-save');

        await expect(emittedSave?.[0][0]).rejects.toThrow('Could not save');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(true);
    });

    it('cancels inline edits by reloading the table without saving', async () => {
        const editableRecords = [
            {
                id: 'record-1',
                name: 'First record',
            },
        ];
        const repository = createRepositoryMock(createSearchResult(editableRecords));
        const wrapper = createWrapper({
            props: {
                repository,
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await wrapper.find('.sw-data-grid-inline-edit-stub').setValue('Updated record');
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cancel').trigger('click');

        const emittedCancel = wrapper.emitted('inline-edit-cancel');

        expect(getSaveMock(repository)).not.toHaveBeenCalled();
        expect(emittedCancel?.[0][0]).toBeInstanceOf(Promise);

        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(false);
    });

    it('renders custom slots for editable columns while the row is not being edited', async () => {
        let forwardedSlotScope: Record<string, unknown> | undefined;
        const wrapper = createWrapper({
            props: {
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
            slots: {
                'column-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScope = slotProps;

                    return h('span', { class: 'inline-column-name-slot' }, (slotProps.item as TestRecord).name);
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('.inline-column-name-slot').text()).toBe('First record');
        expect(forwardedSlotScope?.data).toEqual(records[0]);
        expect(forwardedSlotScope?.item).toEqual(records[0]);
        expect(forwardedSlotScope?.columnDefinition).toEqual(expect.objectContaining(inlineEditColumns[0]));
        expect(forwardedSlotScope?.column).toEqual(expect.objectContaining(inlineEditColumns[0]));
        expect(forwardedSlotScope?.isInlineEdit).toBe(false);
    });

    it('renders legacy preview slots before editable column content', async () => {
        let forwardedSlotScope: Record<string, unknown> | undefined;
        const repository = createRepositoryMock(
            createSearchResult([
                {
                    id: 'record-1',
                    name: 'First record',
                    previewUrl: '/preview.png',
                },
            ]),
        );
        const wrapper = createWrapper({
            props: {
                repository,
                columns: [
                    {
                        label: 'Name',
                        property: 'name',
                        previewImage: 'previewUrl',
                        inlineEdit: 'string',
                    },
                ],
                allowInlineEdit: true,
            },
            slots: {
                'preview-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScope = slotProps;

                    return h(
                        'span',
                        { class: 'legacy-preview-name-slot' },
                        `${(slotProps.item as TestRecord).name}:${String(slotProps.compact)}`,
                    );
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('.legacy-preview-name-slot').text()).toBe('First record:false');
        expect(wrapper.find('.sw-meteor-entity-data-table__text-renderer').text()).toBe('First record');
        expect(wrapper.find('.sw-meteor-entity-data-table__preview-image-renderer').exists()).toBe(false);
        expect(forwardedSlotScope?.data).toEqual(expect.objectContaining({ id: 'record-1' }));
        expect(forwardedSlotScope?.item).toEqual(expect.objectContaining({ id: 'record-1' }));
        expect(forwardedSlotScope?.columnDefinition).toEqual(expect.objectContaining({ property: 'name' }));
        expect(forwardedSlotScope?.column).toEqual(expect.objectContaining({ property: 'name' }));
        expect(forwardedSlotScope?.compact).toBe(false);
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

    it('resolves the prepared criteria before searching', async () => {
        const repository = createRepositoryMock();
        const context = {
            ...Shopware.Context.api,
            inheritance: true,
        } as ApiContext;
        const resolvedCriteria = new Criteria(3, 5);
        resolvedCriteria.addFilter(Criteria.equals('active', true));
        let resolverPayload: SwMeteorEntityDataTableCriteriaResolverPayload | undefined;
        const criteriaResolver: SwMeteorEntityDataTableCriteriaResolver = jest.fn((payload) => {
            resolverPayload = payload;
            return resolvedCriteria;
        });

        createWrapper({
            props: {
                repository,
                context,
                criteriaResolver,
                initialPage: 2,
                initialLimit: 10,
                initialSearchTerm: 'shirt',
                initialSort: {
                    property: 'name',
                    direction: 'DESC',
                },
            },
        });

        await flushPromises();

        expect(criteriaResolver).toHaveBeenCalledTimes(1);
        expect(resolverPayload).toBeDefined();

        const payload = resolverPayload as SwMeteorEntityDataTableCriteriaResolverPayload;

        expect(payload.criteria.parse()).toEqual(
            expect.objectContaining({
                page: 2,
                limit: 10,
                term: 'shirt',
                sort: [
                    {
                        field: 'name',
                        order: 'DESC',
                        naturalSorting: false,
                    },
                ],
            }),
        );
        expect(payload.state).toEqual({
            page: 2,
            limit: 10,
            searchTerm: 'shirt',
            sort: {
                property: 'name',
                direction: 'DESC',
            },
        });
        expect(payload.context).toEqual(context);
        expect(getSearchMock(repository)).toHaveBeenCalledWith(resolvedCriteria, context);
    });

    it('emits an empty successful load when the criteria resolver returns null', async () => {
        const repository = createRepositoryMock();
        const criteriaResolver: SwMeteorEntityDataTableCriteriaResolver = jest.fn(() => null);
        const wrapper = createWrapper({
            props: {
                repository,
                criteriaResolver,
            },
        });

        await flushPromises();

        expect(criteriaResolver).toHaveBeenCalledTimes(1);
        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(getTable(wrapper).props('dataSource')).toEqual([]);
        expect(getTable(wrapper).props('paginationTotalItems')).toBe(0);
        expect(wrapper.emitted('load-success')).toEqual([
            [
                {
                    records: [],
                    total: 0,
                    state: {
                        page: 1,
                        limit: 25,
                        searchTerm: '',
                    },
                },
            ],
        ]);
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

    it('adds and removes a single row selection with a legacy-compatible selection-change payload', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
            },
        });

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual(['record-1']);

        await wrapper.find('.mt-data-table-stub__deselect-row').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                {
                    'record-1': records[0],
                },
                1,
            ],
            [
                {},
                0,
            ],
        ]);
        expect(wrapper.emitted('selected-ids-change')).toEqual([
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

        await flushPromises();

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
                {
                    'record-1': records[0],
                },
                1,
            ],
            [
                {
                    'record-1': records[0],
                    'record-2': records[1],
                },
                2,
            ],
            [
                {},
                0,
            ],
        ]);
        expect(wrapper.emitted('selected-ids-change')).toEqual([
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

    it('clears bulk selections when selecting all already selected rows again', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
            },
        });

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([
            'record-1',
            'record-2',
        ]);

        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(wrapper.emitted('selection-change')).toEqual([
            [
                {
                    'record-1': records[0],
                    'record-2': records[1],
                },
                2,
            ],
            [
                {},
                0,
            ],
        ]);
        expect(wrapper.emitted('selected-ids-change')).toEqual([
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

    it('keeps listing mixin selected ID stores in sync through selection-change', async () => {
        const wrapper = createListingConsumerWrapper();

        await flushPromises();

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await nextTick();

        const listingConsumer = wrapper.vm as unknown as { selection: Record<string, TestRecord> };

        expect(listingConsumer.selection).toEqual({
            'record-1': records[0],
        });
        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual(['record-1']);
        expect(Shopware.Store.get('swBulkEdit').selectedIds).toEqual(['record-1']);
    });

    it('opens a bulk delete confirmation modal and deletes selected rows', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                selectable: true,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await wrapper.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(true);

        await wrapper.find('.sw-meteor-entity-data-table__bulk-delete-confirm').trigger('click');
        await flushPromises();

        expect(getSyncDeletedMock(repository)).toHaveBeenCalledWith(
            [
                'record-1',
                'record-2',
            ],
            Shopware.Context.api,
        );
        expect(getDeleteMock(repository)).not.toHaveBeenCalled();
        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(false);
        expect(wrapper.emitted('bulk-delete-finish')).toEqual([
            [
                {
                    ids: [
                        'record-1',
                        'record-2',
                    ],
                },
            ],
        ]);
    });

    it('emits bulk-delete-failed and keeps the modal open when bulk deletion fails', async () => {
        const repository = createRepositoryMock();
        const error = new Error('Bulk delete failed');
        getSyncDeletedMock(repository).mockRejectedValueOnce(error);

        const wrapper = createWrapper({
            props: {
                repository,
                selectable: true,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await wrapper.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await wrapper.find('.sw-meteor-entity-data-table__bulk-delete-confirm').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(getTable(wrapper).props('selectedRows')).toEqual(['record-1']);
        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(true);
        expect(wrapper.emitted('bulk-delete-failed')).toEqual([
            [
                {
                    ids: ['record-1'],
                    error,
                },
            ],
        ]);
    });

    it('does not open the bulk delete modal without selected rows or delete permission', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
                allowDelete: true,
            },
        });
        const wrapperWithoutDeletePermission = createWrapper({
            props: {
                selectable: true,
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await wrapperWithoutDeletePermission.find('.mt-data-table-stub__select-row').trigger('click');
        await wrapperWithoutDeletePermission.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(false);
        expect(wrapperWithoutDeletePermission.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(false);
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

    it('opens a delete confirmation modal and deletes the selected row', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(true);

        await wrapper.find('.sw-meteor-entity-data-table__delete-confirm').trigger('click');
        await flushPromises();

        expect(getDeleteMock(repository)).toHaveBeenCalledWith('record-1', Shopware.Context.api);
        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(false);
        expect(wrapper.emitted('delete-finish')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                },
            ],
        ]);
    });

    it('emits delete-failed and keeps the modal open when deletion fails', async () => {
        const repository = createRepositoryMock();
        const error = new Error('Delete failed');
        getDeleteMock(repository).mockRejectedValueOnce(error);

        const wrapper = createWrapper({
            props: {
                repository,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__delete').trigger('click');
        await wrapper.find('.sw-meteor-entity-data-table__delete-confirm').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(true);
        expect(wrapper.emitted('delete-failed')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                    error,
                },
            ],
        ]);
    });

    it('does not open the delete modal when row deletion is disabled', async () => {
        const wrapper = createWrapper();

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(false);
    });

    it('normalizes context-select events from mt-data-table', async () => {
        const wrapper = createWrapper({
            props: {
                additionalContextButtons: [
                    {
                        key: 'set-price',
                        label: 'Set price',
                    },
                ],
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__context-select').trigger('click');

        expect(wrapper.emitted('context-select')).toEqual([
            [
                {
                    key: 'set-price',
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

    it('forwards custom table column slots with Meteor scope and compatibility aliases', () => {
        let forwardedSlotScope: Record<string, unknown> | undefined;
        const wrapper = createWrapper({
            slots: {
                'column-name': (slotProps: Record<string, unknown>) => {
                    forwardedSlotScope = slotProps;

                    return h('span', { class: 'column-name-slot' }, (slotProps.item as TestRecord).name);
                },
            },
        });

        expect(wrapper.find('.column-name-slot').text()).toBe('First record');
        expect(forwardedSlotScope?.data).toEqual(records[0]);
        expect(forwardedSlotScope?.item).toEqual(records[0]);
        expect(forwardedSlotScope?.columnDefinition).toEqual(expect.objectContaining(columns[0]));
        expect(forwardedSlotScope?.column).toEqual(expect.objectContaining(columns[0]));
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

    it('converts legacy Options API overrides queued through Shopware.Component.override', async () => {
        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

        expect(isNativeShopwareComponentName(componentName)).toBe(true);

        Shopware.Component.override(componentName, {
            methods: {
                setPage(
                    this: {
                        $super: (methodName: string, page: number) => unknown;
                        state: SwMeteorEntityDataTableState;
                    },
                    page: number,
                ) {
                    this.$super('setPage', page);
                    this.state.page += 10;
                },
            },
        });

        const wrapper = createWrapper();
        await flushPromises();
        await nextTick();

        await getTable(wrapper).find('.mt-data-table-stub__page').trigger('click');
        await nextTick();

        expect(getTable(wrapper).props('currentPage')).toBe(13);

        warnSpy.mockRestore();
    });
});
